<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd\presentation\support;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use dbschemix\core\InputOptions;

trait CommandOptions
{
    /**
     * @throws InvalidArgumentException
     */
    protected function getOptions(InputInterface $input): InputOptions
    {
        $options = [];
        if ($input->hasOption('limit')) {
            $options['limit'] = $this->getOptionLimit($input);
        }

        if ($input->hasOption('dry-run')) {
            $options['dryRun'] = $this->getOptionDryRun($input);
        }

        if ($input->hasOption('db')) {
            $options['dbName'] = $this->getOptionDbName($input);
        }

        if ($input->hasOption('with-repeatable')) {
            $options['hasRepeatable'] = $this->getOptionWithRepeatable($input);
        }

        if ($input->hasOption('latest-version')) {
            $options['applyLatestVersion'] = $this->getOptionApplyLatestVersion($input);
        }

        if ($input->hasOption('exactly-all')) {
            $options['exactlyAll'] = $this->getOptionExactlyAll($input);
        }

        if ($input->hasArgument('name')) {
            $options['migrationName'] = $this->getMigrationName($input);
        }

        return new InputOptions(...$options);
    }

    /**
     * Normalizes the raw CLI option into the type required by the core
     * contract. The "limit" business rule itself is owned by
     * {@see InputOptions}, which declares the parameter as a
     * non-negative-int; this method only guarantees the transport value
     * can satisfy that contract before it reaches the core, so a malformed
     * option is rejected at the edge instead of producing an out-of-contract
     * value downstream.
     *
     * @return non-negative-int
     * @throws InvalidArgumentException if the option cannot be represented
     *                                  as the core contract's non-negative-int
     */
    private function getOptionLimit(InputInterface $input): int
    {
        /** @phpstan-ignore cast.int */
        $value = (int)$input->getOption('limit');
        if ($value < 0) {
            throw new InvalidArgumentException('Argument (limit) must be greater than to 0.');
        }

        return $value;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getOptionDryRun(InputInterface $input): bool
    {
        return $input->getOption('dry-run') === true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getOptionApplyLatestVersion(InputInterface $input): bool
    {
        return $input->getOption('latest-version') === true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getOptionExactlyAll(InputInterface $input): bool
    {
        return $input->getOption('exactly-all') === true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getOptionWithRepeatable(InputInterface $input): bool
    {
        return $input->getOption('with-repeatable') === true;
    }

    /**
     * @return ?non-empty-string
     * @throws InvalidArgumentException
     */
    private function getOptionDbName(InputInterface $input): ?string
    {
        $value = $input->getOption('db');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @return ?non-empty-string
     * @throws InvalidArgumentException
     */
    private function getMigrationName(InputInterface $input): ?string
    {
        $value = $input->getArgument('name');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
