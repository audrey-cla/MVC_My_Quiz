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

class QuizController extends AbstractController
{
    public function index()
    {
        $quizzs = $this->getDoctrine()->getRepository(Categorie::class)->findAll();
        return $this->render('quiz/index.html.twig', ['quizzs' => $quizzs]);
    }

    public function show_quiz($id)
    {
        $quiz = $this->getDoctrine()
            ->getRepository(Categorie::class)
            ->find($id);
        if (!$quiz) {
            return $this->render('quiz/index.html.twig');
        }
        return new Response('Check out this great product: ' . $quiz->getName()  . "<a href='/quiz/$id/1'>Commencer le test</a>");
    }

    public function show_question($id, $question_num, Request $request)
    {
        $lastQuestion = $this->getDoctrine()->getRepository(Question::class)->findOneBy(array('idCategorie' => $id), array('id' => 'DESC'));
        $offset = $question_num - 1;
        $question = $this->getDoctrine()->getRepository(Question::class)->findBy(array("idCategorie" => $id), null, 1, $offset);
        $question = $question[0];
        $reponses = $this->getDoctrine()->getRepository(Reponse::class)->findBy(array("idQuestion" => $question->getId()), null);
        $session = new Session();
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
            ->add('save', SubmitType::class, ['label' => 'Create Task'])
            ->getForm();

        if ($request->isMethod('POST')) {
            $form = $form->submit($request->request->get($form->getName()));
            $answer = $form['reponse']->getData();
            if ($form->isSubmitted() && $form->isValid()) {
                if ($reponses[$answer]->getReponseExpected() == true) {
                    $this->addFlash('success', 'BONNE REPONSE');
                } else {
                    $this->addFlash('error', 'MAUVAISE REPONSE');
                }
                if ($question == $lastQuestion) {
                    return $this->render('results.html.twig');
                } else {
                    $next = $question_num + 1;
                    return $this->render('reponse.html.twig', ['id' => $id, 'next' => $next]);
                }
            }
        }

        return $this->render('question.html.twig', ['form' => $form->createView(), 'question' => $question->getQuestion()]);
    }
}
