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

    #[Test]
    public function eleve_cannot_access_messaging(): void
    {
        [, $token] = $this->userWithRole('Élève');

        $this->withToken($token)->getJson('/api/admin/messages')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/messages/recipients')->assertForbidden();
        $this->withToken($token)->postJson('/api/admin/messages', [])->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_send_or_read_messages(): void
    {
        [$sender] = $this->userWithRole('Parent');
        [$recipient] = $this->userWithRole('Professeur');
        [, $token] = $this->userWithRole('Caissier');

        $message = $this->createMessage($sender, $recipient);

        $this->withToken($token)->getJson("/api/admin/messages/{$message->id}")->assertForbidden();
        $this->withToken($token)->postJson('/api/admin/messages', [
            'recipient_id' => $recipient->id,
            'subject' => 'Test',
            'body' => '...',
        ])->assertForbidden();
    }

    #[Test]
    public function admin_can_send_message_to_teacher_and_read_it_back(): void
    {
        [$admin, $adminToken] = $this->userWithRole('Administrator');
        [$teacher] = $this->userWithRole('Professeur');

        $response = $this->withToken($adminToken)->postJson('/api/admin/messages', [
            'recipient_id' => $teacher->id,
            'subject' => 'Réunion pédagogique',
            'body' => 'Rendez-vous lundi.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sender_id', $admin->id)
            ->assertJsonPath('data.direction', 'sent')
            ->assertJsonPath('data.recipient.id', $teacher->id);

        $this->withToken($adminToken)
            ->getJson('/api/admin/messages/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.subject', 'Réunion pédagogique');
    }

    #[Test]
    public function parent_cannot_send_message_to_unauthorized_roles(): void
    {
        [, $parentToken] = $this->userWithRole('Parent');
        [$otherParent] = $this->userWithRole('Parent');
        [$cashier] = $this->userWithRole('Caissier');
        [$student] = $this->userWithRole('Élève');

        foreach ([$otherParent, $cashier, $student] as $recipient) {
            $this->withToken($parentToken)
                ->postJson('/api/admin/messages', [
                    'recipient_id' => $recipient->id,
                    'subject' => 'Bonjour',
                    'body' => '...',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('recipient_id');
        }

        $this->assertSame(0, Message::query()->count());
    }

    #[Test]
    public function teacher_cannot_send_message_to_another_teacher(): void
    {
        [, $teacherToken] = $this->userWithRole('Professeur');
        [$otherTeacher] = $this->userWithRole('Professeur');

        $this->withToken($teacherToken)
            ->postJson('/api/admin/messages', [
                'recipient_id' => $otherTeacher->id,
                'subject' => 'Bonjour',
                'body' => '...',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('recipient_id');
    }

    #[Test]
    public function user_cannot_send_message_to_self(): void
    {
        [$admin, $adminToken] = $this->userWithRole('Administrator');

        $this->withToken($adminToken)
            ->postJson('/api/admin/messages', [
                'recipient_id' => $admin->id,
                'subject' => 'Moi',
                'body' => '...',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('recipient_id');
    }

    #[Test]
    public function send_validates_required_fields_and_student_context(): void
    {
        [, $parentToken] = $this->userWithRole('Parent');
        [$teacher] = $this->userWithRole('Professeur');

        $this->withToken($parentToken)
            ->postJson('/api/admin/messages', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id', 'subject', 'body']);

        $this->withToken($parentToken)
            ->postJson('/api/admin/messages', [
                'recipient_id' => $teacher->id,
                'subject' => 'Sujet',
                'body' => '...',
                'student_context_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('student_context_id');
    }

    #[Test]
    public function inbox_exposes_participants_direction_and_unread_count(): void
    {
        [$parent, $parentToken] = $this->userWithRole('Parent');
        [$teacher] = $this->userWithRole('Professeur');

        $this->createMessage($parent, $teacher, 'Envoyé');
        $this->createMessage($teacher, $parent, 'Reçu');

        $response = $this->withToken($parentToken)->getJson('/api/admin/messages');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 1);

        $bySubject = collect($response->json('data'))->keyBy('subject');

        $this->assertSame('sent', $bySubject['Envoyé']['direction']);
        $this->assertSame('received', $bySubject['Reçu']['direction']);
        $this->assertSame($teacher->id, $bySubject['Reçu']['sender']['id']);
        $this->assertArrayNotHasKey('password', $bySubject['Reçu']['sender']);
    }

    #[Test]
    public function teacher_inbox_excludes_other_teachers_conversations(): void
    {
        [$parent] = $this->userWithRole('Parent');
        [$teacherA, $teacherAToken] = $this->userWithRole('Professeur');
        [$teacherB] = $this->userWithRole('Professeur');

        $this->createMessage($parent, $teacherA, 'Pour A');
        $this->createMessage($parent, $teacherB, 'Pour B');

        $this->withToken($teacherAToken)
            ->getJson('/api/admin/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Pour A');
    }

    #[Test]
    public function admin_cannot_read_messages_between_other_users(): void
    {
        [$parent] = $this->userWithRole('Parent');
        [$teacher] = $this->userWithRole('Professeur');
        [, $adminToken] = $this->userWithRole('Administrator');

        $message = $this->createMessage($parent, $teacher);

        $this->withToken($adminToken)->getJson('/api/admin/messages')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($adminToken)->getJson("/api/admin/messages/{$message->id}")->assertForbidden();
    }

    #[Test]
    public function sender_viewing_message_does_not_mark_it_as_read(): void
    {
        [$sender, $senderToken] = $this->userWithRole('Parent');
        [$recipient] = $this->userWithRole('Professeur');

        $message = $this->createMessage($sender, $recipient);

        $this->withToken($senderToken)
            ->getJson("/api/admin/messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('data.direction', 'sent');

        $this->assertNull($message->fresh()->read_at);
    }

    #[Test]
    public function show_returns_404_for_unknown_message(): void
    {
        [, $token] = $this->userWithRole('Parent');

        $this->withToken($token)->getJson('/api/admin/messages/999999')->assertNotFound();
    }

    #[Test]
    public function recipients_are_filtered_by_role(): void
    {
        [, $parentToken] = $this->userWithRole('Parent');
        [, $teacherToken] = $this->userWithRole('Professeur');
        $this->userWithRole('Caissier');
        $this->userWithRole('Élève');
        $this->userWithRole('Administrator');

        $parentRoles = collect($this->withToken($parentToken)->getJson('/api/admin/messages/recipients')
            ->assertOk()->json('data'))->pluck('role')->unique()->sort()->values()->all();
        $teacherRoles = collect($this->withToken($teacherToken)->getJson('/api/admin/messages/recipients')
            ->assertOk()->json('data'))->pluck('role')->unique()->sort()->values()->all();

        $this->assertSame(['Administrator', 'Professeur'], $parentRoles);
        $this->assertSame(['Administrator', 'Parent'], $teacherRoles);
    }

    #[Test]
    public function recipient_with_several_roles_is_listed_once_with_his_allowed_roles(): void
    {
        [, $parentToken] = $this->userWithRole('Parent');
        [$teacherManager] = $this->userWithRole('Professeur');
        $this->assignRole($teacherManager, 'Manager');
        $this->assignRole($teacherManager, 'Caissier');

        $response = $this->withToken($parentToken)->getJson('/api/admin/messages/recipients?limit=50')
            ->assertOk()
            ->assertJsonPath('meta.allowed_roles', ['Professeur', 'Administrator', 'Manager']);

        $entries = collect($response->json('data'))->where('id', $teacherManager->id)->values();

        $this->assertCount(1, $entries);
        $this->assertSame(['Manager', 'Professeur'], $entries[0]['roles']);
        $this->assertSame('Manager', $entries[0]['role']);
        $this->assertSame(
            collect($response->json('data'))->pluck('id')->unique()->count(),
            count($response->json('data'))
        );
    }

    #[Test]
    public function recipients_meta_describes_roles_reachable_by_the_sender(): void
    {
        [, $teacherToken] = $this->userWithRole('Professeur');
        [, $adminToken] = $this->userWithRole('Administrator');

        $this->withToken($teacherToken)->getJson('/api/admin/messages/recipients')
            ->assertOk()->assertJsonPath('meta.allowed_roles', ['Parent', 'Administrator', 'Manager']);
        $this->withToken($adminToken)->getJson('/api/admin/messages/recipients')
            ->assertOk()->assertJsonPath('meta.allowed_roles', ['Parent', 'Professeur', 'Administrator', 'Manager']);
    }

    #[Test]
    public function manager_can_send_message_to_parent_and_read_inbox(): void
    {
        [$manager, $managerToken] = $this->userWithRole('Manager');
        [$parent, $parentToken] = $this->userWithRole('Parent');

        $this->withToken($managerToken)
            ->postJson('/api/admin/messages', [
                'recipient_id' => $parent->id,
                'subject' => 'Convocation',
                'body' => 'Merci de passer au secrétariat.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender_id', $manager->id)
            ->assertJsonPath('data.recipient_id', $parent->id);

        $this->withToken($managerToken)
            ->getJson('/api/admin/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'sent');

        $this->withToken($parentToken)
            ->getJson('/api/admin/messages')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.sender.id', $manager->id);
    }

    #[Test]
    public function manager_receives_messages_from_parents_and_teachers(): void
    {
        [$manager, $managerToken] = $this->userWithRole('Manager');
        [, $parentToken] = $this->userWithRole('Parent');
        [, $teacherToken] = $this->userWithRole('Professeur');

        foreach ([$parentToken, $teacherToken] as $token) {
            $this->withToken($token)
                ->postJson('/api/admin/messages', [
                    'recipient_id' => $manager->id,
                    'subject' => 'Question',
                    'body' => '...',
                ])
                ->assertCreated();
        }

        $response = $this->withToken($managerToken)->getJson('/api/admin/messages');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 2);

        $this->withToken($managerToken)
            ->getJson('/api/admin/messages/'.$response->json('data.0.id'))
            ->assertOk()
            ->assertJsonPath('data.direction', 'received');
    }

    #[Test]
    public function manager_recipients_match_administrator_rules(): void
    {
        [, $managerToken] = $this->userWithRole('Manager');
        $this->userWithRole('Parent');
        $this->userWithRole('Professeur');
        $this->userWithRole('Administrator');
        $this->userWithRole('Caissier');
        $this->userWithRole('Élève');

        $roles = collect($this->withToken($managerToken)->getJson('/api/admin/messages/recipients?limit=50')
            ->assertOk()->json('data'))->pluck('role')->unique()->sort()->values()->all();

        $this->assertSame(['Administrator', 'Parent', 'Professeur'], $roles);
    }

    #[Test]
    public function manager_cannot_send_message_to_cashier_or_student(): void
    {
        [, $managerToken] = $this->userWithRole('Manager');
        [$cashier] = $this->userWithRole('Caissier');
        [$student] = $this->userWithRole('Élève');

        foreach ([$cashier, $student] as $recipient) {
            $this->withToken($managerToken)
                ->postJson('/api/admin/messages', [
                    'recipient_id' => $recipient->id,
                    'subject' => 'Bonjour',
                    'body' => '...',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('recipient_id');
        }
    }

    #[Test]
    public function messaging_requires_authentication(): void
    {
        $this->getJson('/api/admin/messages')->assertUnauthorized();
    }

    private function createMessage(TenantUser $sender, TenantUser $recipient, string $subject = 'Sujet'): Message
    {
        return Message::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'subject' => $subject,
            'body' => '...',
        ]);
    }
}
