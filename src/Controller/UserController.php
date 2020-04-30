<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Categorie;
use App\Entity\Score;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class UserController extends AbstractController
{
    public function dashboard()
    {

        if ($this->getUser()->getRoles() == "ROLE_USER") {
            return $this->render('user/index.html.twig');
        } else {
            return $this->render('admin/admindashboard.html.twig');
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
            $this->addFlash('success', 'Adresse vérifiée');
            return $this->render('user/index.html.twig');

        } else {
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
          
            $this->addFlash('success', 'changements effectués');
            return $this->render('user/edit.html.twig', ['form' => $form->createView(), 'user_data' => $user]);
        }
        return $this->render('user/edit.html.twig', ['form' => $form->createView(), 'user_data' => $user]);
    }

    public function show_profile($id)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->find($id);
        $user_id = $user->getID();
        $avant = $this->getDoctrine()->getRepository(Score::class)->findBy(array("user_id" => $user_id));
        $total = array();
        foreach ($avant as $test) {
            $id = $test->getCategorieId();
            $score = $test->getScore();
            $name = $this->getDoctrine()->getRepository(Categorie::class)->find($id);
            $name = $name->getName();
            array_push($total, ["name" => $name, "id" => "$id", "score" => $score]);
        }
        return $this->render('user/profile.html.twig', ['user_data' => $user, 'scores' => $total]);
    }

    public function show_all()
    {
        $users = $this->getDoctrine()->getRepository(User::class)->findAll();

        return $this->render('user/all.html.twig', ['users' => $users]);
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
}
