<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(MailerInterface $mailer, Request $request, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, LoginFormAuthenticator $authenticator): Response
    {
        $user = new User();

        $role = $user->getRoles();

        $form = $this->createForm(RegistrationFormType::class, $user);
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
            $user->setRoles($role);
            $entityManager = $this->getDoctrine()->getManager();
            $user->setValidated($specialtoken);
            // $user->setUsername($form->get('username')->getData());

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

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
