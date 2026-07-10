<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    /**
     * The profile form must expose NO submittable, editable email field. Email is
     * immutable here (it is the shared-table login identifier), and the controller/request
     * enforce that server-side — but the form must not *invite* an edit that silently does
     * nothing. The email is shown read-only (no `name` attribute → never submitted).
     *
     * This makes the Blade's honesty load-bearing rather than incidental: re-adding
     * name="email" fails this test. Asserted against the fully rendered /profile page
     * (the error bag is only present on the real request, not a bare view()->render()).
     */
    public function test_the_profile_form_exposes_no_editable_email_field(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com']);

        $html = $this->actingAs($user)->get('/profile')->getContent();

        // No submittable email field at all.
        $this->assertStringNotContainsString('name="email"', $html,
            'the profile form must not submit an email field');

        // But the address is still shown (read-only) for reference...
        $this->assertStringContainsString('real@example.com', $html);
        // ...in a field that cannot be typed into.
        $this->assertMatchesRegularExpression('/<input[^>]*id="email"[^>]*(disabled|readonly)/i', $html);
    }

    /**
     * ProfileController::update() deliberately strips `email` from the validated data
     * before filling the model ("Ensure email isn't updated"), so the profile form can
     * change the name but not the address you sign in with. The stock Breeze test
     * assumed the opposite.
     *
     * Because the email never becomes dirty, the controller's `email_verified_at = null`
     * branch is unreachable, and a verified user stays verified across a profile edit.
     */
    public function test_profile_name_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
    }

    public function test_the_profile_form_cannot_change_the_email_address(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $originalVerifiedAt = $user->email_verified_at;

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'attacker@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame($originalEmail, $user->email, 'the profile form must not change the sign-in address');
        $this->assertEquals($originalVerifiedAt, $user->email_verified_at, 'and must not revoke email verification');
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
