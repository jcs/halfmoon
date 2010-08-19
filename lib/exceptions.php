<?php

namespace HalfMoon;

class HalfMoonException extends \Exception {};

class RoutingException extends HalfMoonException {};
class RenderException extends HalfMoonException {};
class InvalidAuthenticityToken extends HalfMoonException {};
class InvalidCookieData extends HalfMoonException {};

?>
