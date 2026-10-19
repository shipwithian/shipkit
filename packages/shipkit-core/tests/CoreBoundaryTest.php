<?php

arch('the shared kernel never depends on the application that installs it')
    ->expect('ShipKit\Core')
    ->not->toUse('App');
