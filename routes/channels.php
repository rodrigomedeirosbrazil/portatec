<?php

use Illuminate\Support\Facades\Broadcast;

// Canal público para sincronização de dispositivos
Broadcast::channel('device-sync.{chipId}', function () {
    return true; // Canal público, sem autenticação
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('Place.Device.Status.{placeId}', function ($user, $placeId) {
    return $user->hasRole('super_admin')
        || $user->placeUsers()->where('place_id', (int) $placeId)->exists();
});

Broadcast::channel('Place.Device.Command.Ack.{placeId}', function ($user, $placeId) {
    return $user->hasRole('super_admin')
        || $user->placeUsers()->where('place_id', (int) $placeId)->exists();
});
