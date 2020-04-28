<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class UserController extends AbstractController
{
    public function dashboard()
    {
        return $this->render('user/index.html.twig');
    }

    public function validation($id, $token)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(array('id' => $id));
        $validation = $user->getValidated();
        $entityManager = $this->getDoctrine()->getManager();
        if ($validation == '1') {
            return new Response(" Adresse déja vérifiée ! ");
        } else if ($validation == $token) {

            $user->setValidated('1');
            $entityManager->persist($user);
            $entityManager->flush();
            return new Response(" Adresse vérifiée ! ");
        } else {
            return new Response(" error: no match ");
        }
    }

    public function edit(MailerInterface $mailer, Request $request, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, LoginFormAuthenticator $authenticator)
    {

        $user = $this->getUser();
        $form = $this->createFormBuilder();
        $form = $form->add("Username", TextType::class, ['data' => $user->getUsername()])
        ->add("email", TextType::class, ['data' => $user->getEmail()])
        ->add("plainPassword", TextType::class)
        ->add('save', SubmitType::class, ['label' => 'Valider le quiz']);
        $form = $form->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordEncoder->encodePassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            $specialtoken = $user->getEmail();
            $specialtoken = hash('MD5', $specialtoken);

            $entityManager = $this->getDoctrine()->getManager();
            $user->setValidated($specialtoken);
            $entityManager->persist($user);
            $entityManager->flush();

            $id = $user->getId();

            $email = (new Email())
                ->from('quiveutgagnerdelargentenmasse@example.com')
                ->to($user->getEmail())
                ->cc('mailtrapqa@example.com')
                ->html('validez votre compte en cliquant sur le  <a href="http://127.0.0.1:8000/validation/' . $id . '/' . $specialtoken . '">lien suivant</a>');
            $mailer->send($email);

            return $guardHandler->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $authenticator,
                'main' // firewall name in security.yaml
            );
        }


        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($user);
        $entityManager->flush();



        return $this->render('user/profile.html.twig',['form' => $form->createView()]);
    }


}
