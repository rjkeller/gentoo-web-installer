<?php
/**
 * Gentoo Server Manager Project
 * Copyright (C) 2014, Roger L Keller (rjkellercode@pixonite.com),
 * All rights reserved.
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3.0 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.
 */
namespace Pixonite\ServerManagerBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pixonite\ServerManagerBundle\Entity\IpAddress;
use Pixonite\ServerManagerBundle\Form\IpAddressType;

/**
 * IpAddress controller.
 *
 * @Route("/ip-address")
 */
class IpAddressController extends Controller
{

    /**
     * Lists all IpAddress entities.
     *
     * @Route("/", name="ip-address")
     * @Method("GET")
     * @Template()
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('PixoniteServerManagerBundle:IpAddress')->findAll();

        return array(
            'entities' => $entities,
        );
    }
    /**
     * Creates a new IpAddress entity.
     *
     * @Route("/", name="ip-address_create")
     * @Method("POST")
     * @Template("PixoniteServerManagerBundle:IpAddress:new.html.twig")
     */
    public function createAction(Request $request)
    {
        $entity = new IpAddress();
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('ip-address_show', array('id' => $entity->getId())));
        }

        return array(
            'entity' => $entity,
            'form'   => $form->createView(),
        );
    }

    /**
    * Creates a form to create a IpAddress entity.
    *
    * @param IpAddress $entity The entity
    *
    * @return \Symfony\Component\Form\Form The form
    */
    private function createCreateForm(IpAddress $entity)
    {
        $form = $this->createForm(new IpAddressType(), $entity, array(
            'action' => $this->generateUrl('ip-address_create'),
            'method' => 'POST',
        ));

        $form->add('submit', 'submit', array('label' => 'Create'));

        return $form;
    }

    /**
     * Displays a form to create a new IpAddress entity.
     *
     * @Route("/new", name="ip-address_new")
     * @Method("GET")
     * @Template()
     */
    public function newAction()
    {
        $entity = new IpAddress();
        $form   = $this->createCreateForm($entity);

        return array(
            'entity' => $entity,
            'form'   => $form->createView(),
        );
    }

    /**
     * Finds and displays a IpAddress entity.
     *
     * @Route("/{id}", name="ip-address_show")
     * @Method("GET")
     * @Template()
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('PixoniteServerManagerBundle:IpAddress')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find IpAddress entity.');
        }

        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity'      => $entity,
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Displays a form to edit an existing IpAddress entity.
     *
     * @Route("/{id}/edit", name="ip-address_edit")
     * @Method("GET")
     * @Template()
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('PixoniteServerManagerBundle:IpAddress')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find IpAddress entity.');
        }

        $editForm = $this->createEditForm($entity);
        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
    * Creates a form to edit a IpAddress entity.
    *
    * @param IpAddress $entity The entity
    *
    * @return \Symfony\Component\Form\Form The form
    */
    private function createEditForm(IpAddress $entity)
    {
        $form = $this->createForm(new IpAddressType(), $entity, array(
            'action' => $this->generateUrl('ip-address_update', array('id' => $entity->getId())),
            'method' => 'PUT',
        ));

        $form->add('submit', 'submit', array('label' => 'Update'));

        return $form;
    }
    /**
     * Edits an existing IpAddress entity.
     *
     * @Route("/{id}", name="ip-address_update")
     * @Method("PUT")
     * @Template("PixoniteServerManagerBundle:IpAddress:edit.html.twig")
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('PixoniteServerManagerBundle:IpAddress')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find IpAddress entity.');
        }

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $em->flush();

            return $this->redirect($this->generateUrl('ip-address_edit', array('id' => $id)));
        }

        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }
    /**
     * Deletes a IpAddress entity.
     *
     * @Route("/{id}", name="ip-address_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $form = $this->createDeleteForm($id);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('PixoniteServerManagerBundle:IpAddress')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Unable to find IpAddress entity.');
            }

            $em->remove($entity);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('ip-address'));
    }

    /**
     * Creates a form to delete a IpAddress entity by id.
     *
     * @param mixed $id The entity id
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm($id)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('ip-address_delete', array('id' => $id)))
            ->setMethod('DELETE')
            ->add('submit', 'submit', array('label' => 'Delete'))
            ->getForm()
        ;
    }
}
