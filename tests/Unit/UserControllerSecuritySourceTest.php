<?php

namespace Tests\Unit;

use Tests\TestCase;

class UserControllerSecuritySourceTest extends TestCase
{
    public function test_user_listing_does_not_select_password_hash()
    {
        $source = file_get_contents(app_path('Http/Controllers/UserController.php'));

        $this->assertStringNotContainsString("'users.password'", $source);
        $this->assertStringNotContainsString('"users.password"', $source);
    }

    public function test_user_update_only_changes_password_when_present()
    {
        $source = file_get_contents(app_path('Http/Controllers/UserController.php'));

        $this->assertStringContainsString("if (\$request->filled('password'))", $source);
        $this->assertStringContainsString("Rule::unique('users', 'usuario')->ignore(\$request->id)", $source);
    }
}
