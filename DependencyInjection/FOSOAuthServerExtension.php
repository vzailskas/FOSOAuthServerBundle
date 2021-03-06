<?php

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\OAuthServerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\Config\FileLocator;

class FOSOAuthServerExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load(sprintf('%s.xml', $config['db_driver']));

        foreach (array('oauth', 'security') as $basename) {
            $loader->load(sprintf('%s.xml', $basename));
        }

        $container->setAlias('fos_oauth_server.storage', $config['service']['storage']);
        $container->setAlias('fos_oauth_server.client_manager', $config['service']['client_manager']);
        $container->setAlias('fos_oauth_server.access_token_manager', $config['service']['access_token_manager']);
        $container->setAlias('fos_oauth_server.refresh_token_manager', $config['service']['refresh_token_manager']);
        $container->setAlias('fos_oauth_server.auth_code_manager', $config['service']['auth_code_manager']);

        if (null !== $config['service']['user_provider']) {
            $container->setAlias('fos_oauth_server.user_provider', new Alias($config['service']['user_provider'], false));
        }

        $container->setParameter('fos_oauth_server.server.options', $config['service']['options']);

        $this->remapParametersNamespaces($config, $container, array(
            '' => array(
                'model_manager_name'    => 'fos_oauth_server.model_manager_name',
                'client_class'          => 'fos_oauth_server.model.client.class',
                'access_token_class'    => 'fos_oauth_server.model.access_token.class',
                'refresh_token_class'   => 'fos_oauth_server.model.refresh_token.class',
                'auth_code_class'       => 'fos_oauth_server.model.auth_code.class',
            ),
            'template' => 'fos_oauth_server.template.%s',
        ));

        // Document manager factory definition
        if ('mongodb' === $config['db_driver']) {
            $odmDocumentManagerDefinition = $container->getDefinition('fos_oauth_server.document_manager');
            if (method_exists($odmDocumentManagerDefinition, 'setFactory')) {
                $odmDocumentManagerDefinition->setFactory(array(new Reference('doctrine_mongodb'), 'getManager'));
            } else {
                $odmDocumentManagerDefinition->setFactoryService('doctrine_mongodb');
                $odmDocumentManagerDefinition->setFactoryMethod('getManager');
            }
        }

        // Entity manager factory definition
        // TODO: Go back to xml configuration when bumping the requirement to Symfony >=2.6
        if ('orm' === $config['db_driver']) {
            $ormEntityManagerDefinition = $container->getDefinition('fos_oauth_server.entity_manager');
            if (method_exists($ormEntityManagerDefinition, 'setFactory')) {
                $ormEntityManagerDefinition->setFactory(array(new Reference('doctrine'), 'getManager'));
            } else {
                $ormEntityManagerDefinition->setFactoryService('doctrine');
                $ormEntityManagerDefinition->setFactoryMethod('getManager');
            }
        }

        if (!empty($config['authorize'])) {
            $this->loadAuthorize($config['authorize'], $container, $loader);
        }

        // Authorize form factory definition
        // TODO: Go back to xml configuration when bumping the requirement to Symfony >=2.6
        $authorizeFormDefinition = $container->getDefinition('fos_oauth_server.authorize.form');
        if (method_exists($authorizeFormDefinition, 'setFactory')) {
            $authorizeFormDefinition->setFactory(array(new Reference('form.factory'), 'createNamed'));
        } else {
            $authorizeFormDefinition->setFactoryService('form.factory');
            $authorizeFormDefinition->setFactoryMethod('createNamed');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'fos_oauth_server';
    }

    protected function remapParameters(array $config, ContainerBuilder $container, array $map)
    {
        foreach ($map as $name => $paramName) {
            if (array_key_exists($name, $config)) {
                $container->setParameter($paramName, $config[$name]);
            }
        }
    }

    protected function remapParametersNamespaces(array $config, ContainerBuilder $container, array $namespaces)
    {
        foreach ($namespaces as $ns => $map) {
            if ($ns) {
                if (!array_key_exists($ns, $config)) {
                    continue;
                }
                $namespaceConfig = $config[$ns];
            } else {
                $namespaceConfig = $config;
            }

            if (is_array($map)) {
                $this->remapParameters($namespaceConfig, $container, $map);
            } else {
                foreach ($namespaceConfig as $name => $value) {
                    $container->setParameter(sprintf($map, $name), $value);
                }
            }
        }
    }

    protected function loadAuthorize(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('authorize.xml');

        $container->setAlias('fos_oauth_server.authorize.form.handler', $config['form']['handler']);
        unset($config['form']['handler']);

        $this->remapParametersNamespaces($config, $container, array(
            'form' => 'fos_oauth_server.authorize.form.%s',
        ));
    }
}
