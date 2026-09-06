<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_follows_browser_then_explicit_choice_and_is_remembered(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])->get('/login')->assertOk()->assertSee('Se connecter');
        $this->withHeaders(['Accept-Language' => 'zh-CN,zh;q=0.9'])->get('/login')->assertOk()->assertSee('登录');

        $this->get('/login?lang=en')->assertOk()->assertSee('Log in')->assertSessionHas('locale', 'en');
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_language_is_saved_on_the_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard?lang=zh')->assertOk();
        $this->assertSame('zh', $user->fresh()->locale);
        $this->actingAs($user)->withHeaders(['Accept-Language' => 'fr'])->get('/dashboard')->assertOk()->assertSee('快捷入口');
    }
}
