<?php

namespace App;

class HttpVerbs {
    public const OK = 200;
    public const CREATED = 201;
    public const SUCCESS_NO_CONTENT = 204;
    public const BAD_REQUEST = 400;
    public const UNAUTHENTICATED = 401;
    public const UNAUTHORIZED_REQUEST = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const NOT_ACCEPTABLE = 406;
    public const INTERNAL_SERVER_ERROR = 500;
    public const NOT_IMPLEMENTED = 501;
    public const BAD_GATEWAY = 502;
    public const SERVICE_UNAVAILABLE = 503;
    public const GATEWAY_TIMEOUT = 504;
    public const HTTP_UNAVAILABLE = 505;
}
