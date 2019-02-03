<?php

function pusher($channel = 'main:default')
{
    return \clients\pusher\Svc::getInstance($channel);
}
