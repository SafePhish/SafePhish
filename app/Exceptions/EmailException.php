<?php
/**
 * Created by PhpStorm.
 * User: tthrockmorton
 * Date: 7/14/2016
 * Time: 4:10 PM
 */

namespace app\Exceptions;

use PhpSpec\Exception\Exception;


class EmailException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message,$code,$previous);
    }
}