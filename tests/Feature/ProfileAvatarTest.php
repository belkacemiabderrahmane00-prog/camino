<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_jpeg_avatar_is_stored_as_square_jpeg(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('moi.jpg', 640, 480);

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $file,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');
        $user->refresh();
        $this->assertSame('image/jpeg', $user->avatar_mime);
        $this->assertNotEmpty($user->avatar);
        [$w, $h] = getimagesizefromstring($user->avatar);
        $this->assertSame([512, 512], [$w, $h]);

        $this->get(route('users.avatar', $user))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }
}
