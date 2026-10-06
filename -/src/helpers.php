<?php

function pusher($channel = 'default', $env = false)
{
    return \clients\pusher\ChannelSvc::getInstance($channel, $env);
}
