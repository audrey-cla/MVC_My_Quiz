<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Categorie;
use App\Entity\Score;
use App\Entity\Question;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use App\Repository\ScoreRepository;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class UserController extends AbstractController
{
    public function dashboard()
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $scores = $this->getDoctrine()->getRepository(Score::class)->findAll();
            $membrestotal = $this->getDoctrine()->getRepository(User::class)->findAll();
            $quiztotal = $this->getDoctrine()->getRepository(Categorie::class)->findAll();
            $questiontotal = $this->getDoctrine()->getRepository(Question::class)->findAll();
            return $this->render('admin/admindashboard.html.twig', ["scores" => count($scores), "membres" => count($membrestotal), "quizz" => count($quiztotal), "questions" => count($questiontotal)]);
        } else {
            return $this->render('user/index.html.twig');
        }
    }

    public function validation($id, $token)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(array('id' => $id));
        $validation = $user->getValidated();
        $entityManager = $this->getDoctrine()->getManager();
        if ($validation == '1') {
            $this->addFlash('error', 'Adresse déja vérifiée');
            return $this->render('user/index.html.twig');
        } else if ($validation == $token) {
            $user->setValidated('1');
            $entityManager->persist($user);
            $entityManager->flush();
            $this->get('session')->getFlashBag()->clear();
            $this->addFlash('success', 'Adresse vérifiée');
            return $this->render('user/index.html.twig');
        } else {
            $this->get('session')->getFlashBag()->clear();
            $this->addFlash('error', 'No match');
            return $this->render('user/index.html.twig');
        }
    }

    public function edit($id, MailerInterface $mailer, Request $request, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, LoginFormAuthenticator $authenticator)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->find($id);
        $form = $this->createFormBuilder();
        $form = $form->add("username", TextType::class, ['data' => $user->getUsername()])
            ->add("email", TextType::class, ['data' => $user->getEmail()])
            ->add("role", ChoiceType::class, [
                'choices'  => [
                    "User" => "ROLE_USER",
                    "Admin" => "ROLE_ADMIN",
                ],
                'expanded' => false,
                'multiple' => false,
            ])
            ->add("password", TextType::class, ["required" => false])
            ->add('save', SubmitType::class, ['label' => 'Effectuer les changements']);
        $form = $form->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!empty($form->get('password')->getData())) {
                $user->setPassword(
                    $passwordEncoder->encodePassword(
                        $user,
                        $form->get('password')->getData()
                    )
                );
            }

            if ($user->getEmail() == $form->get("email")->getData()) {
                $specialtoken = 1;
            } else {
                $specialtoken = hash('MD5', $form->get("email")->getData());
                $email = (new Email())
                    ->from('quiveutgagnerdelargentenmasse@example.com')
                    ->to($form->get("email")->getData())
                    ->cc('mailtrapqa@example.com')
                    ->html('validez votre compte en cliquant sur le  <a href="http://127.0.0.1:8000/validation/' . $user->getId() . '/' . $specialtoken . '">lien suivant</a>');
                $mailer->send($email);
                $user->setEmail($form->get("email")->getData());
            }

            if ($user->getRoles()[0] != $form->get("role")->getData()) {
                $user->setRoles([$form->get("role")->getData()]);
            } else {
                $user->setRoles([$form->get("role")->getData()]);
            }

            $user->setUsername($form->get("username")->getData());
            $user->setValidated($specialtoken);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();
            $this->get('session')->getFlashBag()->clear();
            $this->addFlash('yesedit', 'changements effectués');
            return $this->render('user/edit.html.twig', ['form' => $form->createView(), 'user_data' => $user]);
        }
        return $this->render('user/edit.html.twig', ['form' => $form->createView(), 'user_data' => $user]);
    }

    public function show_profile($id)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->find($id);
        $avant = $this->getDoctrine()->getRepository(Score::class)->findBy(array("user_id" => $user->getID()));
        $total = array();
        foreach ($avant as $test) {
            $id = $test->getCategorieId();
            $score = $test->getScore();
            $name = $this->getDoctrine()->getRepository(Categorie::class)->find($id);
            $name = $name->getName();
            array_push($total, ["name" => $name, "id" => "$id", "score" => $score]);
        }
        $stats = [];
        $quizfait = $this->getDoctrine()->getRepository(Score::class)->findBy(array("user_id" => $user->getID()));
        $quiztotal = $this->getDoctrine()->getRepository(Categorie::class)->findAll();
        array_push($stats, ["quizfait" => count($quizfait), "quiztotal" => count($quiztotal)]);
        return $this->render('user/profile.html.twig', ['user_data' => $user, 'scores' => $total, 'stats' => $stats]);
    }

    public function show_all()
    {
        return $this->render('user/all.html.twig', ['users' => $this->getDoctrine()->getRepository(User::class)->findAll()]);
    }

    public function delete($id)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->find($id);
        $scores = $this->getDoctrine()->getRepository(Score::class)->findBy(array("user_id" => $id));
        $entityManager = $this->getDoctrine()->getManager();
        foreach ($scores as $score) {
            $entityManager->remove($score);
        }
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->render('user/index.html.twig');
    }

    public function mailing(Request $request, MailerInterface $mailer)
    {
        $categories = $this->getDoctrine()->getRepository(Categorie::class)->findAll();
        $count = count($categories);
        $form = $this->createFormBuilder();
        $form = $form->add("choix", ChoiceType::class, [
            'choices'  => [
                "Inclure" => 0,
                "Exclure" => 1,
                "A tous" => 2,
            ],
            'expanded' => true,
            'multiple' => false
        ]);
        $tableau = [];
        for ($x = 0; $x < $count; $x++) {
            $nom = $categories[$x]->getName();
            $id = $categories[$x]->getId();
            array_push($tableau, [$nom => $id]);
        }
        $form = $form->add("board", ChoiceType::class, [
            'choices'  => $tableau,
            'expanded' => false,
            'multiple' => false
        ]);
        $form = $form->add("message", TextareaType::class);
        $form = $form->add('save', SubmitType::class, ['label' => 'Valider le quiz']);
        $form = $form->getForm();
        $form->handleRequest($request);
        if ($request->isMethod('POST')) {
            if ($form->isSubmitted() && $form->isValid()) {
                switch ($form->get("choix")->getData()) {
                    case 0:
                        $sendTo = $this->getDoctrine()->getRepository(Score::class)->findBy(array("categorie_id" => $form->get("board")->getData()));
                        break;
                    case 1:
                        // $exclure = $this->getDoctrine()->getManager()->getRepository(Score::class)->findByNot('id');                      
                        break;
                    case 2:
                        $sendTo = $this->getDoctrine()->getRepository(Score::class)->findAll();
                        break;
                }
                foreach ($sendTo as $user) {
                    $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(array("id" => $user->getUserId()));
                    $email = (new Email())
                        ->from('quiveutgagnerdelargentenmasse@example.com')
                        ->to($user->getEmail())
                        ->cc('mailtrapqa@example.com')
                        ->html($form->get("message")->getData());
                    $mailer->send($email);
                }
            }
        }



        return $this->render('admin/mailer.html.twig', ['form' => $form->createView()]);
    }
}
