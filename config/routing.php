<?php

use Symfony\Component\HttpFoundation\Request;

/**
 * homepage
 */
$app->get('/', function () use ($app)
        {
            return $app['twig']->render('home.twig', array(
                    ));
        })->bind('homepage');

/**
 * contacts
 */
$app->match('/contacts', function (Request $request) use ($app)
        {
            $data = array(
                'name' => '',
                'email' => '',
            );

            $form = $app['form.factory']->createBuilder('form', $data)
                    ->add('name', 'text')
                    ->add('email', 'text')
                    ->add('message', 'textarea')
                    ->getForm();

            if ('POST' == $request->getMethod())
            {
                $form->bind($request);

                if ($form->isValid())
                {
                    $data = $form->getData();
                    $app->register(new Silex\Provider\SwiftmailerServiceProvider());
                    $message = \Swift_Message::newInstance()
                        ->setSubject('Tito Miguel Costa @ Contact')
                        ->setFrom(array($data['email']))
                        ->setTo(array('contact@titomiguelcosta.com'))
                        ->setBody($app['twig']->render('email/contact.twig', array('data' => $data)));

                    $app['session']->setFlash('name', $data['name']);

                    return $app->redirect('/contacts');
                }
            }
            return $app['twig']->render('contact.twig', array(
                        'form' => $form->createView(),
                        'name' => $name
                    ));
        })->bind('contact');
/**
 * request
 */
$app->match('/request', function (Request $request) use ($app)
        {
            $submit = false;
            $data = array(
                'name' => '',
                'email' => '',
            );

            $form = $app['form.factory']->createBuilder('form', $data)
                    ->add('name', 'text')
                    ->add('email', 'text')
                    ->add('message', 'textarea')
                    ->getForm();

            if ('POST' == $request->getMethod())
            {
                $form->bind($request);

                if ($form->isValid())
                {
                    $app->register(new Silex\Provider\SwiftmailerServiceProvider());

                    $message = \Swift_Message::newInstance();
                    $app['mailer']->send($message);

                    $data = $form->getData();
                    $submit = true;

                    return $app->redirect('/contact');
                }
            }
            return $app['twig']->render('contact.twig', array(
                        'form' => $form->createView()
                    ));
        })->bind('request');