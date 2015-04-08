<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Setup\Console\Command;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Module\ModuleList;
use Magento\Setup\Model\ConfigModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigSetCommand extends Command
{
    /**
     * @var ConfigModel
     */
    protected $configModel;

    /**
     * Enabled module list
     *
     * @var ModuleList
     */
    private $moduleList;

    /**
     * Existing deployment config
     */
    private $deploymentConfig;

    /**
     * Constructor
     *
     * @param \Magento\Setup\Model\ConfigModel $configModel
     * @param ModuleList $moduleList
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        ConfigModel $configModel,
        ModuleList $moduleList,
        DeploymentConfig $deploymentConfig
    ) {
        $this->configModel = $configModel;
        $this->moduleList = $moduleList;
        $this->deploymentConfig = $deploymentConfig;
        parent::__construct();
    }

    /**
     * Initialization of the command
     *
     * @return void
     */
    protected function configure()
    {
        $options = $this->configModel->getAvailableOptions();

        $this->setName('setup:config:set')
            ->setDescription('Sets deployment configuration')
            ->setDefinition($options);

        $this->ignoreValidationErrors();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputOptions = $input->getOptions();
        $optionCollection = $this->configModel->getAvailableOptions();
        $commandOptions = [];
        $optionsWithDefaultValues = [];

        foreach ($optionCollection as $option) {
            $commandOptions[$option->getName()] = false;

            $currentValue = $this->deploymentConfig->get($option->getConfigPath());
            if (($currentValue !== null) && ($inputOptions[$option->getName()] !== null)) {
                $dialog = $this->getHelperSet()->get('dialog');
                if (!$dialog->askConfirmation(
                    $output,
                    '<question>Overwrite the existing configuration for ' . $option->getName() . '?[Y|n]</question>'
                )) {
                    $inputOptions[$option->getName()] = null;
                }
            }

            if ($option->getDefault() === $inputOptions[$option->getName()]
                && $inputOptions[$option->getName()] !== null
            ) {
                $optionsWithDefaultValues[] = $option->getName();
            }
        }

        $inputOptions = array_filter(
            $inputOptions,
            function ($value) {
                return $value !== null;
            }
        );

        $optionsToChange = array_intersect(array_keys($inputOptions), array_keys($commandOptions));

        $this->configModel->process($inputOptions);

        if (count($optionsWithDefaultValues) > 0) {
            $defaultValuesMessage = implode(', ', $optionsWithDefaultValues);
            $output->writeln(
                '<info>We saved default values for these options: ' . $defaultValuesMessage . '.</info>'
            );
        } else {
            if (count($optionsToChange) > 0) {
                $output->writeln('<info>You saved the new configuration.</info>');
            } else {
                $output->writeln('<info>You made no changes to the configuration.</info>');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $inputOptions = $input->getOptions();

        $errors = $this->configModel->validate($inputOptions);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $output->writeln("<error>$error</error>");
            }
            throw new \InvalidArgumentException('Parameters validation is failed');
        }
    }
}