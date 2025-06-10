<?php

namespace Wazza\DbEncrypt\Http\Controllers;

use Wazza\DbEncrypt\Http\Controllers\Logger\LogController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * The logger instance
     *
     * @var LogController
     */
    public $logger;

    /**
     * Create a new CrmController instance.
     *
     * @param string|null $logIdentifier
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct(?string $logIdentifier = null)
    {
        // set the logger instance
        $this->logger = new LogController($logIdentifier);
    }
}
