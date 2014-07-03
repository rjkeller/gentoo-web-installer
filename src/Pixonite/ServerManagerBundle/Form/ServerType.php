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
namespace Pixonite\ServerManagerBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ServerType extends AbstractType
{
        /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('publicIp', 'text', array('required' => false))
            ->add('hostname')
            ->add('status', 'choice', array(
                'choices' => array(
                    'PENDING_CREATE_START' => 'PENDING_CREATE_START',
                    'PENDING_CREATE' => 'PENDING_CREATE',
                    'ACTIVE' => 'ACTIVE',
                    'INACTIVE' => 'INACTIVE',
                ),
                'empty_value' => '-- Select status --',
            ))
            ->add('siteId')
            ->add('kernel', 'choice', array(
                'choices' => array(
                    '3_13_vmware_hardened' => 'Kernel 3.13 VMWare Hardened',
                    '3_13_virtio_hardened' => 'Kernel 3.13 VirtIO Hardened',
                    '3_13_vmware' => 'Kernel 3.13 VMWare',
                    '3_13_core2quad' => 'Kernel 3.13 Core2Quad',
                    '3_13_bluebox' => 'Kernel 3.13 BlueBox',
                    '3_12_virtio_hardened' => 'Kernel 3.12 VirtIO Hardened',
                    '3_12_vmware_hardened' => 'Kernel 3.12 VMWare Hardened',
                    '3_12_virtio_systemd' => 'Kernel 3.12 VirtIO',
                    '3_10_virtio' => 'Kernel 3.10 VirtIO (OpenRC)',
                    '3_10_vmware' => 'Kernel 3.10 VMWare (OpenRC)',
                ),
                'empty_value' => '-- Select Kernel --',
            ))
            ->add('eth0_name')
            ->add('disks')
            ->add('enableRaid')
            ->add('numCpus')
            ->add('inputDevices', 'text', array('required' => false))
            ->add('videoCards', 'text', array('required' => false))
            ->add('siteMode', 'choice', array(
                'choices' => array(
                    'dev' => 'dev',
                    'stage' => 'stage',
                    'live' => 'live',
                )))
        ;
    }
    
    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Pixonite\ServerManagerBundle\Entity\Server'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Pixonite_servermanagerbundle_server';
    }
}
