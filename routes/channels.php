<?php

use Illuminate\Support\Facades\Broadcast;

// Canal público para sincronização de dispositivos
Broadcast::channel('device-sync.{chipId}', function () {
    return true; // Canal público, sem autenticação
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('Place.Device.Status.{placeId}', function ($placeId) {
    return true; // TODO: check if user has access to this place
});
