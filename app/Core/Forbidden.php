<?php
namespace App\Core;

/** Thrown when the signed-in user lacks a permission. */
class Forbidden extends \RuntimeException
{
}
