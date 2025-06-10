<?php

namespace Wazza\DbEncrypt\Http\Controllers;

use Wazza\DbEncrypt\Http\Controllers\BaseController;
use Wazza\DbEncrypt\Models\EncryptedAttributes;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Container\BindingResolutionException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Exception;

/**
 * Sync Class CrmController
 * Example: (new CrmController())->setModel($user)->execute();
 *
 * @package Wazza\DbEncrypt\Http\Controllers
 * @version 1.0.0
 * @todo convert the log class to be injected into the controller instead of using the facade
 */

class DnEncryptController extends BaseController
{
    /**
     * Create a new CrmController instance and define the log identifier (blank will create a new one)
     *
     * @param string|null $logIdentifier
     * @return void
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct(?string $logIdentifier = null)
    {
        // parent constructor
        parent::__construct($logIdentifier);

        // ...
    }
}
