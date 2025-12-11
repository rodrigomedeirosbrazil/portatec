<?php

use Illuminate\Support\Facades\Broadcast;


Broadcast::channel('device-sync.{chipId}', function () {
    return true;
});

Broadcast::channel('place.{placeId}', function () {
    return true;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('Place.Device.Status.{placeId}', function ($placeId) {
    return true; // TODO: check if user has access to this place
});

Broadcast::channel('Place.Device.Command.Ack.{placeId}', function ($placeId) {
    return true; // TODO: check if user has access to this place
});
