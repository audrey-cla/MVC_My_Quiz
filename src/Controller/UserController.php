<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

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
}
