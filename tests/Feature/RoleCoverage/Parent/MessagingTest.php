<?php

namespace Tests\Feature\RoleCoverage\Parent;

use Modules\Messaging\Entities\Message;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story Parent 07 — Messages Parent ↔ Enseignants (mini-module Messaging).
 *
 * Couvre :
 *   - Envoi de message Parent → Prof
 *   - Inbox de chaque utilisateur (sender ou recipient)
 *   - Marquage automatique read_at quand recipient consulte
 *   - Cross-user : un autre Parent ne voit pas les messages
 */
class MessagingTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function userWithRole(string $role): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return [$user, $user->createToken('test-token')->plainTextToken];
    }

    #[Test]
    public function parent_can_send_message_to_teacher(): void
    {
        [$parent, $parentToken] = $this->userWithRole('Parent');
        [$teacher] = $this->userWithRole('Professeur');

        $this->withToken($parentToken)
            ->postJson('/api/admin/messages', [
                'recipient_id' => $teacher->id,
                'subject' => 'Demande info Alice',
                'body' => 'Bonjour, comment se passe sa scolarité ?',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.sender_id', $parent->id)
            ->assertJsonPath('data.recipient_id', $teacher->id);
    }

    #[Test]
    public function teacher_can_reply_to_parent(): void
    {
        [$parent] = $this->userWithRole('Parent');
        [$teacher, $teacherToken] = $this->userWithRole('Professeur');

        $this->withToken($teacherToken)
            ->postJson('/api/admin/messages', [
                'recipient_id' => $parent->id,
                'subject' => 'Re: scolarité Alice',
                'body' => 'Tout se passe bien.',
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function user_sees_only_own_messages_in_inbox(): void
    {
        [$parentA, $parentAToken] = $this->userWithRole('Parent');
        [$parentB] = $this->userWithRole('Parent');
        [$teacher] = $this->userWithRole('Professeur');

        // Message A → Prof (visible pour A)
        Message::create([
            'sender_id' => $parentA->id,
            'recipient_id' => $teacher->id,
            'subject' => 'A→Prof',
            'body' => '...',
        ]);

        // Message B → Prof (NON visible pour A)
        Message::create([
            'sender_id' => $parentB->id,
            'recipient_id' => $teacher->id,
            'subject' => 'B→Prof',
            'body' => '...',
        ]);

        $response = $this->withToken($parentAToken)->getJson('/api/admin/messages');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function recipient_show_marks_message_as_read(): void
    {
        [$sender] = $this->userWithRole('Parent');
        [$recipient, $recipientToken] = $this->userWithRole('Professeur');

        $message = Message::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => 'Test',
            'body' => '...',
        ]);

        $this->assertNull($message->read_at);

        $this->withToken($recipientToken)
            ->getJson("/api/admin/messages/{$message->id}")
            ->assertOk();

        $this->assertNotNull($message->fresh()->read_at);
    }

    #[Test]
    public function user_cannot_view_other_users_message(): void
    {
        [$sender] = $this->userWithRole('Parent');
        [$recipient] = $this->userWithRole('Professeur');
        [, $otherToken] = $this->userWithRole('Parent');

        $message = Message::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => 'Privé',
            'body' => '...',
        ]);

        $this->withToken($otherToken)
            ->getJson("/api/admin/messages/{$message->id}")
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_access_messaging(): void
    {
        [, $token] = $this->userWithRole('Caissier');

        $this->withToken($token)
            ->getJson('/api/admin/messages')
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_messaging(): void
    {
        [, $token] = $this->userWithRole('Étudiant');

        $this->withToken($token)
            ->getJson('/api/admin/messages')
            ->assertForbidden();
    }
}
