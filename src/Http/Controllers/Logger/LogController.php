<?php

namespace Wazza\DbEncrypt\Http\Controllers\Logger;

use Illuminate\Support\Facades\Log;

final class LogController
{
    // define the log types (alert, critical, debug, emergency, error, info, notice, warning)
    public const TYPE__ALERT = 'alert';
    public const TYPE__CRITICAL = 'critical';
    public const TYPE__DEBUG = 'debug';
    public const TYPE__EMERGENCY = 'emergency';
    public const TYPE__ERROR = 'error';
    public const TYPE__INFO = 'info';
    public const TYPE__NOTICE = 'notice';
    public const TYPE__WARNING = 'warning';

    // define the log levels (0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level)
    public const LEVEL__NONE = 0;
    public const LEVEL__HIGH = 1;
    public const LEVEL__MID = 2;
    public const LEVEL__LOW = 3;

    /**
     * log identifier - ideally should be between block brackets and a short hash like crc32
     * @var string
     */
    private $logIdentifier;

    /**
     * LogController constructor
     * The constructor primarily sets the log identifier which ideally should be between block brackets
     *
     * @param string|null $identifier The log identifier for session tracking - i.e. using grep to find related logs
     */
    public function __construct(?string $identifier = null)
    {
        $this->setLogIdentifier($identifier);
    }

    // --------------------------------------------------------------------------
    // Getters and Setters
    // --------------------------------------------------------------------------

    /**
     * Get Log Identifier
     *
     * @return string
     */
    public function getLogIdentifier(): string
    {
        return $this->logIdentifier;
    }

    /**
     * Set Log Identifier
     *
     * @param string|null $identifier The log identifier for session tracking - i.e. using grep to find related logs
     */
    public function setLogIdentifier(?string $identifier = null, $append = false): void
    {
        // no identifier provided, generate one
        if (empty($identifier)) {
            $this->logIdentifier = '[' . hash('crc32', microtime(true) . rand(10000, 99999)) . ']';
            return;
        }

        // make sure the identified is set between block brackets
        if (substr($identifier, 0, 1) !== '[') {
            $identifier = '[' . $identifier;
        }
        if (substr($identifier, -1) !== ']') {
            $identifier = $identifier . ']';
        }

        // set the identifier
        if ($append) {
            $this->logIdentifier .= $identifier;
        } else {
            $this->logIdentifier = $identifier;
        }
    }

    // --------------------------------------------------------------------------
    // Alert Logging
    // --------------------------------------------------------------------------

    /**
     * Log Alert
     *
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function alert(int $level = self::LEVEL__HIGH, string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->log(self::TYPE__ALERT, $level, $string, $context, $logIdentifier);
    }

    // --------------------------------------------------------------------------
    // Critical Logging
    // --------------------------------------------------------------------------

    /**
     * Log Critical
     *
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function critical(int $level = self::LEVEL__HIGH, string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->log(self::TYPE__CRITICAL, $level, $string, $context, $logIdentifier);
    }

    /**
     * Log Critical High
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function criticalHigh(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->critical(self::LEVEL__HIGH, $string, $context, $logIdentifier);
    }

    /**
     * Log Critical Mid
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function criticalMid(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->critical(self::LEVEL__MID, $string, $context, $logIdentifier);
    }

    /**
     * Log Critical Low
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function criticalLow(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->critical(self::LEVEL__LOW, $string, $context, $logIdentifier);
    }

    // --------------------------------------------------------------------------
    // Debug Logging
    // --------------------------------------------------------------------------

    /**
     * Log Debug
     *
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function debug(int $level = self::LEVEL__HIGH, string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->log(self::TYPE__DEBUG, $level, $string, $context, $logIdentifier);
    }

    /**
     * Log Debug High
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function debugHigh(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->debug(self::LEVEL__HIGH, $string, $context, $logIdentifier);
    }

    /**
     * Log Debug Mid
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function debugMid(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->debug(self::LEVEL__MID, $string, $context, $logIdentifier);
    }

    /**
     * Log Debug Low
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function debugLow(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->debug(self::LEVEL__LOW, $string, $context, $logIdentifier);
    }

    // --------------------------------------------------------------------------
    // Emergency Logging
    // --------------------------------------------------------------------------

    /**
     * Log Emergency
     *
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function emergency(int $level = self::LEVEL__HIGH, string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->log(self::TYPE__EMERGENCY, $level, $string, $context, $logIdentifier);
    }

    /**
     * Log Emergency High
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function emergencyHigh(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->emergency(self::LEVEL__HIGH, $string, $context, $logIdentifier);
    }

    /**
     * Log Emergency Mid
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function emergencyMid(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->emergency(self::LEVEL__MID, $string, $context, $logIdentifier);
    }

    /**
     * Log Emergency Low
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function emergencyLow(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->emergency(self::LEVEL__LOW, $string, $context, $logIdentifier);
    }

    // --------------------------------------------------------------------------
    // Error Logging
    // --------------------------------------------------------------------------

    /**
     * Log Error
     *
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function error(int $level = self::LEVEL__HIGH, string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->log(self::TYPE__ERROR, $level, $string, $context, $logIdentifier);
    }

    /**
     * Log Error High
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function errorHigh(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->error(self::LEVEL__HIGH, $string, $context, $logIdentifier);
    }

    /**
     * Log Error Mid
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function errorMid(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->error(self::LEVEL__MID, $string, $context, $logIdentifier);
    }

    /**
     * Log Error Low
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function errorLow(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->error(self::LEVEL__LOW, $string, $context, $logIdentifier);
    }

    // --------------------------------------------------------------------------
    // Info Logging
    // --------------------------------------------------------------------------

    /**
     * Log Info
     *
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function info(int $level = self::LEVEL__HIGH, string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->log(self::TYPE__INFO, $level, $string, $context, $logIdentifier);
    }

    /**
     * Log Info High
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function infoHigh(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->info(self::LEVEL__HIGH, $string, $context, $logIdentifier);
    }

    /**
     * Log Info Mid
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function infoMid(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->info(self::LEVEL__MID, $string, $context, $logIdentifier);
    }

    /**
     * Log Info Low
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function infoLow(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->info(self::LEVEL__LOW, $string, $context, $logIdentifier);
    }

    // --------------------------------------------------------------------------
    // Notice Logging
    // --------------------------------------------------------------------------

    /**
     * Log Notice
     *
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function notice(int $level = self::LEVEL__HIGH, string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->log(self::TYPE__NOTICE, $level, $string, $context, $logIdentifier);
    }

    /**
     * Log Notice High
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function noticeHigh(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->notice(self::LEVEL__HIGH, $string, $context, $logIdentifier);
    }

    /**
     * Log Notice Mid
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function noticeMid(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->notice(self::LEVEL__MID, $string, $context, $logIdentifier);
    }

    /**
     * Log Notice Low
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function noticeLow(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->notice(self::LEVEL__LOW, $string, $context, $logIdentifier);
    }

    // --------------------------------------------------------------------------
    // Warning Logging
    // --------------------------------------------------------------------------

    /**
     * Log Warning
     *
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function warning(int $level = self::LEVEL__HIGH, string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->log(self::TYPE__WARNING, $level, $string, $context, $logIdentifier);
    }

    /**
     * Log Warning High
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function warningHigh(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->warning(self::LEVEL__HIGH, $string, $context, $logIdentifier);
    }

    /**
     * Log Warning Mid
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function warningMid(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->warning(self::LEVEL__MID, $string, $context, $logIdentifier);
    }

    /**
     * Log Warning Low
     *
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function warningLow(string $string = "", array $context = [], ?string $logIdentifier = null)
    {
        $this->warning(self::LEVEL__LOW, $string, $context, $logIdentifier);
    }

    // --------------------------------------------------------------------------
    // Main Log Method (all other methods will call this one)
    // --------------------------------------------------------------------------

    /**
     * Static Log Controller
     *
     * @param string $type The type of logging i.e. alert, critical, debug, emergency, error, info, notice, warning
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public function log(
        string $type,
        int $level = self::LEVEL__HIGH,
        string $string = "",
        array $context = [],
        ?string $logIdentifier = null,
    ) {
        // load the config
        $logConf = config('db-encrypt.logging');

        // make sure the type is allowed - default to `info`
        if (!in_array($type, ['alert', 'critical', 'debug', 'emergency', 'error', 'info', 'notice', 'warning'])) {
            $type = self::TYPE__INFO;
        }

        // make sure the level is allowed - default to `high`
        if (!in_array($level, [self::LEVEL__NONE, self::LEVEL__HIGH, self::LEVEL__MID, self::LEVEL__LOW])) {
            $level = self::LEVEL__HIGH;
        }

        // make sure we can log - take the config level into account
        if ($level <= $logConf['level'] && $level > 0) {
            // construct the log string
            $logString = '[' . $logConf['indicator'] . ']';
            $logString .= $this->logIdentifier;

            // if $logIdentifier is provided and it already has block brackets, don't add another set when appending to $logString
            if (!empty($logIdentifier)) {
                if (substr($logIdentifier, 0, 1) === '[' && substr($logIdentifier, -1) === ']') {
                    $logString .= $logIdentifier;
                } else {
                    $logString .= '[' . $logIdentifier . ']';
                }
            }

            // if the first letter of $string is '[' then don't add a space
            if (substr($string, 0, 1) !== '[') {
                $logString .= ' ';
            }

            // add the string
            $logString .= $string;

            // log it
            Log::$type($logString, $context);
        }
    }

    // --------------------------------------------------------------------------
    // public static function
    // --------------------------------------------------------------------------

    /**
     * Log It - Static Method
     *
     * @param string $type The type of logging i.e. alert, critical, debug, emergency, error, info, notice, warning
     * @param int $level The desired log level. 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
     * @param string $string String containing the log text
     * @param array $context The log Context i.e Log::info('User {id} failed to login.', ['id' => $user->id]);
     * @param string|null $logIdentifier An additional log identifier for this method call only
     * @return void
     */
    public static function logIt(
        string $type,
        int $level = self::LEVEL__HIGH,
        string $string = "",
        array $context = [],
        ?string $logIdentifier = null,
    ) {
        (new LogController($logIdentifier))->log($type, $level, $string, $context);
    }
}
