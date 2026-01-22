<?php

declare(strict_types=1);

namespace ImageBanks;

interface MultipleInstanceProviderInterface
    {
    public function parseInstancesConfiguration(): array;

    /**
     * @return array<ProviderInstanceInterface>
     */
    public function getAllInstances(): array;

    /**
     * Activate a selected Providers' system instance so the search performed by the Provider is ran over the correct
     * system.
     *
     * @param int $id Instance ID
     */
    public function selectSystemInstance(int $id): Provider;

    /**
     * Get a Provider instance (while working with a particular Provider)
     */
    public function getSelectedSystemInstance(): ProviderInstanceInterface;
    }
