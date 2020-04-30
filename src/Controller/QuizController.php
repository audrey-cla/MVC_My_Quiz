<?php

namespace App\Controller;
use App\Entity\Categorie;
use App\Entity\Question;
use App\Entity\Reponse;
use App\Entity\Score;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class QuizController extends AbstractController
{
    public function index()
    {
        $quizzs = $this->getDoctrine()->getRepository(Categorie::class)->findAll();
        return $this->render('quiz/index.html.twig', ['quizzs' => $quizzs]);
    }

    public function createQuiz(Request $request, $id = 10)
    {
        $form = $this->createFormBuilder();
        $form = $form->add("Titre", TextType::class);
        for ($x = 1; $x <= $id; $x++) {
            $form = $form
                ->add("question:$x", TextType::class, ['label' => "Question $x"])
                ->add("reponse:$x" . ":1", TextType::class, ['label' => "Réponse A"])
                ->add("reponse:$x" . ":2", TextType::class, ['label' => "Réponse B"])
                ->add("reponse:$x" . ":3", TextType::class, ['label' => "Réponse C"])
                ->add("bonne:$x", ChoiceType::class, [
                    'choices'  => [
                        "Reponse A" => 1,
                        "Reponse B" => 2,
                        "Reponce C" => 3,
                    ],
                    'expanded' => true,
                    'multiple' => false
                ]);
        }
        $form = $form->add('save', SubmitType::class, ['label' => 'Valider le quiz']);
        $form = $form->getForm();
        $form->handleRequest($request);
        $data = $form->getData();
        if ($request->isMethod('POST')) {
            if ($form->isSubmitted() && $form->isValid()) {
                $registerQuiz = new Categorie();
                $registerQuiz->setName($form->get("Titre")->getData());
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($registerQuiz);
                $entityManager->flush();
                $catid = $registerQuiz->getID();
                for ($x = 1; $x <= $id; $x++) {
                    foreach ($data as $question => $valeurques) {
                        if ($question == "question:$x") {
                            $bonnerep = $form->get("bonne:$x")->getData();
                            $registerQues = new Question();
                            $registerQues->setQuestion($valeurques);
                            $registerQues->setIdCategorie($catid);
                            $entityManager = $this->getDoctrine()->getManager();
                            $entityManager->persist($registerQues);
                            $entityManager->flush();
                            $tmp = 1;
                            foreach ($data as $reponse => $valeurrep) {
                                if (strpos($reponse, "reponse:$x") === 0) {
                                    $CheckGoodAnswer = 0;
                                    if ($bonnerep == $tmp) {
                                        $CheckGoodAnswer = 1;
                                    }
                                    $tmp++;
                                    $registerQuiz = new Reponse();
                                    $registerQuiz->setIdQuestion($registerQues->getID());
                                    $registerQuiz->setReponse($valeurrep);
                                    $registerQuiz->setReponseExpected($CheckGoodAnswer);
                                    $entityManager = $this->getDoctrine()->getManager();
                                    $entityManager->persist($registerQuiz);
                                    $entityManager->flush();
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this->render('quiz/create.html.twig', ['form' => $form->createView()]);
    }

    public function show_quiz($id, Request $request)
    {
        if ($request->getSession()) {
            $session = $request->getSession();
        } else {
            $session = new Session(new NativeSessionStorage(), new NamespacedAttributeBag());
            $session->start();
        }

        $quiz = $this->getDoctrine()
            ->getRepository(Categorie::class)
            ->find($id);
        if (!$quiz) {
            return $this->render('index.html.twig');
        }

        if ($session->get('quizz' . $id)) {
            return new Response(" Vous avez déja effectué ce test et obtenu un score de " . $session->get('quizz' . $id) . ". Voulez-vous recommencer ? <br><a href='/quiz/$id/1'>Recommencer le test</a>");
        } else {

            return new Response('Bienvenue sur le quiz ' . $quiz->getName()  . "! <br><a href='/quiz/$id/1'>Commencer le test</a>");
            return $this->render('quiz/quiz.html.twig');

        }
    }

    public function show_question($id, $question_num, Request $request)
    {
        if ($request->getSession()) {
            $session = $request->getSession();
        } else {
            $session = new Session();
            $session->start();
        }
        $lastQuestion = $this->getDoctrine()->getRepository(Question::class)->findOneBy(array('idCategorie' => $id), array('id' => 'DESC'));
        $offset = $question_num - 1;
        $question = $this->getDoctrine()->getRepository(Question::class)->findBy(array("idCategorie" => $id), null, 1, $offset);
        $question = $question[0];
        $reponses = $this->getDoctrine()->getRepository(Reponse::class)->findBy(array("idQuestion" => $question->getId()), null);

        $csrfGenerator = new UriSafeTokenGenerator();
        $csrfStorage = new SessionTokenStorage($session);
        $csrfManager = new CsrfTokenManager($csrfGenerator, $csrfStorage);
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrfManager))
            ->getFormFactory();
        $form = $formFactory->createBuilder()
            ->add('reponse', ChoiceType::class, [
                'choices'  => [
                    $reponses[0]->getReponse() => 0,
                    $reponses[1]->getReponse() => 1,
                    $reponses[2]->getReponse() => 2,
                ],
                'expanded' => true,
                'multiple' => false
            ])
            ->add('save', SubmitType::class, ['label' => 'Question suivante'])
            ->getForm();

        if ($request->isMethod('POST')) {
            $form = $form->submit($request->request->get($form->getName()));
            $answer = $form['reponse']->getData();
            if ($form->isSubmitted() && $form->isValid()) {
                if (!$session->get('reponses') || $question_num == 1) {
                    $session->set('reponses', '');
                }

                $answered = $session->get('reponses');
                $answered = (int) $answered;
                if ($reponses[$answer]->getReponseExpected() == true) {
                    $this->addFlash('success', 'BONNE REPONSE');
                    $session->set('reponses', $answered + 1);
                } else {
                    $this->addFlash('error', 'MAUVAISE REPONSE');
                    $session->set('reponses', $answered + 0);
                }

                if ($question == $lastQuestion) {
                    $session->set("quizz/" . $id, $session->get('reponses'));
                    $user = $this->getUser();
                    if ($user != NULL) {
                        $user = $user->getId();
                    }
                    $cherche = $this->getDoctrine()->getRepository(Score::class)->findBy(array("user_id" => $user, "categorie_id" => $id));
                    if ($cherche == NULL) {
                        $registerScore = new Score();
                    } else {
                        $registerScore = $cherche[0];
                    }
                    $registerScore->setUserId($user);
                    $registerScore->setCategorieId($id);
                    $registerScore->setScore($session->get('reponses'));
                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->persist($registerScore);
                    $entityManager->flush();
                    return $this->render('quiz/results.html.twig', ['resultat' => $session->get('reponses'), 'outof' => $question_num]);
                } else {
                    $next = $question_num + 1;
                    return $this->render('quiz/reponse.html.twig', ['id' => $id, 'next' => $next]);
                }
            }
        }
        return $this->render('quiz/question.html.twig', ['form' => $form->createView(), 'question' => $question->getQuestion()]);
    }

    public function score_display(Request $request)
    {
        if ($this->getUser() == NULL) {

            if ($request->getSession()) {
                $session = $request->getSession();
                $scores = $session->all();
                $total = array();
                foreach ($scores as $id => $value) {
                    if (preg_match('#^quizz/#', $id) === 1) {
                        $id = explode("/", $id);
                        $id = $id[1];
                        $name = $this->getDoctrine()->getRepository(Categorie::class)->find($id);
                        $name = $name->getName();
                        array_push($total, ["name" => $name, "id" => "$id", "score" => $value]);
                    }
                }
            }
        } else {
            $user_id = $this->getUser()->getID();
            $avant = $this->getDoctrine()->getRepository(Score::class)->findBy(array("user_id" => $user_id));
            $total = array();
            foreach ($avant as $test) {
                $id = $test->getCategorieId();
                $score = $test->getScore();
                $name = $this->getDoctrine()->getRepository(Categorie::class)->find($id);
                $name = $name->getName();
                array_push($total, ["name" => $name, "id" => "$id", "score" => $score]);
            }
        }
        return $this->render('quiz/scoreboard.html.twig', ['scores' => $total]);
    }


    public function delete($id)
    {
        //  $user = $this->getDoctrine()->getRepository(User::class)->find($id);
        // $scores = $this->getDoctrine()->getRepository(Score::class)->findBy(array("user_id" => $id));
        // $entityManager = $this->getDoctrine()->getManager();
        // foreach ($scores as $score) {
        //     $entityManager->remove($score);
        // }
        // $entityManager->remove($user);


        // $entityManager->flush();
        return $this->render('index.html.twig');
    }
}
