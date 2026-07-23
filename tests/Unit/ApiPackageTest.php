<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ApiPackage;
use PHPUnit\Framework\TestCase;

class ApiPackageTest extends TestCase
{
    public function test_super_admin_is_not_expired_when_package_time_has_passed(): void
    {
        $user = (new User())->forceFill([
            'role' => 1,
            'time_end' => time() - 3600,
        ]);

        $this->assertFalse(ApiPackage::isExpired($user));
    }

    public function test_regular_user_is_expired_when_package_time_has_passed(): void
    {
        $user = (new User())->forceFill([
            'role' => 0,
            'time_end' => time() - 3600,
        ]);

        $this->assertTrue(ApiPackage::isExpired($user));
    }

    public function test_regular_user_with_active_package_is_not_expired(): void
    {
        $user = (new User())->forceFill([
            'role' => 0,
            'time_end' => time() + 3600,
        ]);

        $this->assertFalse(ApiPackage::isExpired($user));
    }
}
