<?php

// src/Controller/LuckyController.php
namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Question;
use App\Entity\Reponse;


use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class QuizController extends AbstractController
{
    public function index()
    {
        $quizzs = $this->getDoctrine()->getRepository(Categorie::class)->findAll();

        // var_dump($quizzs);

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

        return new Response('Check out this great product: ' . $quiz->getName());
    }

    public function show_question($id, $question_num)
    {
        $limit = 1;
        $offset = $question_num - 1;
        $question = $this->getDoctrine()->getRepository(Question::class)->findBy(array("idCategorie" => $id), null, $limit, $offset);
        $question = $question[0];
        $reponses = $this->getDoctrine()->getRepository(Reponse::class)->findBy(array("idQuestion" => $question->getId()), null);



        var_dump($reponses);
        return new Response("ca c'est l'id :" . $id . " et ca c'est la quesiton numero" .   $question_num . "<br> questionnn" . $question->getQuestion());
    }
}
