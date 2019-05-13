<?php

function pusher($channel = 'default')
{
    return \clients\pusher\Svc::getInstance($channel);
}
