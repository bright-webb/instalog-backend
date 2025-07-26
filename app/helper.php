<?php
if (! function_exists('get_client_ip')) {
    function get_client_ip(): string
    {
        return request()->ip();
    }
}


