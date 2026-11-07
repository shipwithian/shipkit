<?php

arch('shipkit-core never depends on the application that installs it')
    ->expect('ShipKit\Core')
    ->not->toUse('App');
