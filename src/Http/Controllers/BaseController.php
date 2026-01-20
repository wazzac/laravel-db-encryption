<?php

namespace Wazza\DbEncrypt\Http\Controllers;

use Wazza\DbEncrypt\Http\Controllers\Logger\LogController;
use Illuminate\Routing\Controller;

class BaseController extends Controller
{
    /**
     * The logger instance
     *
     * @var LogController
     */
    public $logger;

    /**
     * Create a new BaseController instance.
     *
     * @param string|null $logIdentifier
     */
    public function __construct(?string $logIdentifier = null)
    {
        // set the logger instance
        $this->logger = new LogController($logIdentifier);
    }
}
