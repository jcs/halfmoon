<?php

namespace HalfMoon;

class HalfMoonException extends \Exception {};

class UndefinedFunction extends HalfMoonException {};
class MissingTemplate extends HalfMoonException {};
class RoutingException extends HalfMoonException {};
class RenderException extends HalfMoonException {};
class InvalidAuthenticityToken extends HalfMoonException {};
class InvalidCookieData extends HalfMoonException {};
class BadRequest extends HalfMoonException {};

?>
