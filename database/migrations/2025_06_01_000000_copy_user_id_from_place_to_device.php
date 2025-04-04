<?php

use App\Enums\PlaceRoleEnum;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceDevice;
use App\Models\PlaceUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First ensure that user_id column exists in devices table
        if (! Schema::hasColumn('devices', 'user_id')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        // Get all devices that have a place relationship
        $devices = Device::whereHas('placeDevices')->get();

        foreach ($devices as $device) {
            // Get the first place where this device is placed
            $placeDevice = PlaceDevice::where('device_id', $device->id)->first();

            if ($placeDevice) {
                // Get the admin user of this place
                $placeUser = PlaceUser::where('place_id', $placeDevice->place_id)
                    ->where('role', PlaceRoleEnum::Admin->value)
                    ->first();

                if ($placeUser) {
                    // Update the device with the user_id
                    $device->user_id = $placeUser->user_id;
                    $device->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // If this migration added the user_id column, we can remove it
        // Otherwise we just set all user_id values to null
        if (Schema::hasColumn('devices', 'user_id')) {
            // Check if it's safe to drop the column (it might have been added elsewhere)
            // For safety, we'll just null out the values instead of dropping the column
            DB::table('devices')->update(['user_id' => null]);
        }
    }
};
