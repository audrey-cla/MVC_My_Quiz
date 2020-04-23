<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Question;
use App\Entity\Reponse;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
            return new Response(" Vous avez déja effectué ce test et obtenu un score de " . $session->get('quizz' . $id) . ". Voulez-vous recommencer ? <a href='/quiz/$id/1'>Recommencer le test</a>");
        } else {

            return new Response('Check out this great product: ' . $quiz->getName()  . "<a href='/quiz/$id/1'>Commencer le test</a>");
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
                    return $this->render('results.html.twig', ['resultat' => $session->get('reponses'), 'outof' => $question_num]);
                } else {
                    $next = $question_num + 1;
                    return $this->render('reponse.html.twig', ['id' => $id, 'next' => $next]);
                }
            }
        }
        return $this->render('question.html.twig', ['form' => $form->createView(), 'question' => $question->getQuestion()]);
    }


    public function score_display(Request $request)
    {
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
                    array_push($total, ["name"=>$name,"id" => "$id", "score" => $value]);
                }
            }
        }
        
        return $this->render('scoreboard.html.twig', ['scores'=>$total]);
    }
}
