<?php
namespace App\Core;

/** A problem the user can fix (bad input, rule violation). Its message is shown to them. */
class UserError extends \RuntimeException
{
}
