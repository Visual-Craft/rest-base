<?php

declare(strict_types=1);

namespace VisualCraft\RestBaseBundle\DependencyInjection;

use VisualCraft\RestBaseBundle\Controller\ErrorController;
use VisualCraft\RestBaseBundle\EventListener\ZoneMatchListener;
use VisualCraft\RestBaseBundle\Problem\ExceptionToProblemConverterInterface;
use VisualCraft\RestBaseBundle\Problem\ProblemResponseFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\KernelEvents;

class VisualCraftRestBaseExtension extends Extension implements PrependExtensionInterface
{
    private const ZONE_REQUEST_MATCHER_TAG = 'visual_craft.rest_base.zone_request_matcher';
    private const EXCEPTION_TO_PROBLEM_CONVERTER_TAG = 'visual_craft.rest_base.exception_to_problem_converter';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
        $this->configureZoneMatchListener($container, $config['zone']);
        $this->configureProblemBuilders($container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'error_controller' => ErrorController::class,
        ]);
    }

    private function configureZoneMatchListener(ContainerBuilder $container, array $zoneConfig): void
    {
        if (!$zoneConfig) {
            return;
        }

        $listenerDefinition = new Definition(ZoneMatchListener::class);
        $listenerDefinition->addTag('kernel.event_listener', [
            'event' => KernelEvents::REQUEST,
            'method' => 'onKernelRequest',
            'priority' => 248,
        ]);
        $matcherIndex = 0;

        foreach ($zoneConfig as $item) {
            $matcherDefinition = new Definition(RequestMatcher::class);
            $matcherDefinition
                ->setArguments([
                    $item['path'],
                    $item['host'],
                    $item['methods'],
                    $item['ips'],
                ])
                ->addTag(self::ZONE_REQUEST_MATCHER_TAG)
            ;
            $container->setDefinition(self::ZONE_REQUEST_MATCHER_TAG . '.instance_' . $matcherIndex++, $matcherDefinition);
        }

        $listenerDefinition->setArgument('$requestMatchers', new TaggedIteratorArgument(self::ZONE_REQUEST_MATCHER_TAG));
        $container->setDefinition($listenerDefinition->getClass(), $listenerDefinition);
    }

    private function configureProblemBuilders(ContainerBuilder $container): void
    {
        $container
            ->registerForAutoconfiguration(ExceptionToProblemConverterInterface::class)
            ->addTag(self::EXCEPTION_TO_PROBLEM_CONVERTER_TAG)
        ;
        $container->getDefinition(ProblemResponseFactory::class)
            ->setArgument('$exceptionToProblemConverters', new TaggedIteratorArgument(self::EXCEPTION_TO_PROBLEM_CONVERTER_TAG))
        ;
    }
}
