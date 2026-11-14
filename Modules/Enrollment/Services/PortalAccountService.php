<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Entities\ParentModel;
use Modules\UsersGuard\Entities\TenantUser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Comptes de connexion (guard tenant) des élèves et des parents.
 *
 * Le mot de passe est généré côté serveur et renvoyé une seule fois (création et
 * réinitialisation). Seuls les comptes portant exclusivement les rôles Élève/Parent
 * peuvent être gérés ici : les comptes du personnel restent gérés dans « Utilisateurs ».
 */
class PortalAccountService
{
    public const STUDENT_ROLE = 'Élève';

    public const PARENT_ROLE = 'Parent';

    private const PORTAL_ROLES = [self::STUDENT_ROLE, self::PARENT_ROLE];

    /**
     * @return array{account: TenantUser, password: string}
     *
     * @throws ValidationException
     */
    public function createForStudent(Student $student, ?string $username = null): array
    {
        if ($student->user_id && $student->user()->exists()) {
            throw ValidationException::withMessages([
                'account' => ['Cet élève a déjà un compte de connexion.'],
            ]);
        }

        $base = $student->matricule
            ? 'eleve.'.Str::slug($student->matricule)
            : $this->personBase($student->firstname, $student->lastname);

        return DB::connection('tenant')->transaction(function () use ($student, $username, $base): array {
            $password = $this->generatePassword();
            $account = $this->createAccount(
                $username ?? $this->uniqueUsername($base),
                null,
                $password,
                $student->firstname,
                $student->lastname,
                in_array($student->sex, ['M', 'F', 'O'], true) ? $student->sex : null,
                $student->phone,
                self::STUDENT_ROLE
            );

            $student->forceFill(['user_id' => $account->id])->save();

            return ['account' => $account, 'password' => $password];
        });
    }

    /**
     * @return array{account: TenantUser, password: string}
     *
     * @throws ValidationException
     */
    public function createForParent(ParentModel $parent, ?string $username = null): array
    {
        if ($parent->user_id && $parent->user()->exists()) {
            throw ValidationException::withMessages([
                'account' => ['Ce parent a déjà un compte de connexion.'],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($parent, $username): array {
            $password = $this->generatePassword();
            $account = $this->createAccount(
                $username ?? $this->uniqueUsername($this->personBase($parent->firstname, $parent->lastname)),
                $parent->email,
                $password,
                $parent->firstname,
                $parent->lastname,
                match ($parent->relationship) {
                    'Mère', 'Tutrice' => 'F',
                    'Père', 'Tuteur' => 'M',
                    default => null,
                },
                $parent->phone,
                self::PARENT_ROLE
            );

            $parent->forceFill(['user_id' => $account->id])->save();

            return ['account' => $account, 'password' => $password];
        });
    }

    /**
     * Réinitialise le mot de passe et révoque les sessions ouvertes.
     *
     * @throws ValidationException
     */
    public function resetPassword(?TenantUser $account): string
    {
        $account = $this->ensureManageable($account);
        $password = $this->generatePassword();

        $account->forceFill(['password' => $password])->save();
        $account->tokens()->delete();

        return $password;
    }

    /**
     * Active ou désactive le compte (la désactivation révoque les sessions ouvertes).
     *
     * @throws ValidationException
     */
    public function setActive(?TenantUser $account, bool $isActive): TenantUser
    {
        $account = $this->ensureManageable($account);

        $account->forceFill(['is_active' => $isActive])->save();

        if (! $isActive) {
            $account->tokens()->delete();
        }

        return $account;
    }

    /**
     * @return array{id: int, username: string, email: string, is_active: bool, lastlogin: string|null, created_at: string|null, roles: list<string>}|null
     */
    public function present(?TenantUser $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'username' => $account->username,
            'email' => $account->email,
            'is_active' => (bool) $account->is_active,
            'lastlogin' => $account->lastlogin?->toISOString(),
            'created_at' => $account->created_at?->toISOString(),
            'roles' => $account->getRoleNames()->values()->all(),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function ensureManageable(?TenantUser $account): TenantUser
    {
        if (! $account) {
            throw ValidationException::withMessages([
                'account' => ["Aucun compte de connexion n'est associé."],
            ]);
        }

        $otherRoles = $account->getRoleNames()->diff(self::PORTAL_ROLES);

        if ($otherRoles->isNotEmpty()) {
            throw ValidationException::withMessages([
                'account' => ['Ce compte a un rôle du personnel ('.$otherRoles->implode(', ').') : gérez-le depuis « Utilisateurs ».'],
            ]);
        }

        return $account;
    }

    private function createAccount(string $username, ?string $email, string $password, ?string $firstname, ?string $lastname, ?string $sex, ?string $phone, string $role): TenantUser
    {
        if (TenantUser::withTrashed()->where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username' => ["L'identifiant « {$username} » est déjà utilisé."],
            ]);
        }

        if (! $email || TenantUser::withTrashed()->where('email', $email)->exists()) {
            $email = $username.'@'.$this->emailDomain();
        }

        $account = TenantUser::query()->create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'application' => 'frontend',
            'is_active' => true,
            'sex' => $sex,
            'phone' => $phone,
        ]);

        Role::findOrCreate($role, 'tenant');
        $account->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $account;
    }

    private function personBase(?string $firstname, ?string $lastname): string
    {
        $base = Str::slug(trim(($firstname ?? '').' '.($lastname ?? '')), '.');

        return $base !== '' ? $base : 'utilisateur';
    }

    private function uniqueUsername(string $base): string
    {
        $base = Str::limit(Str::lower($base), 60, '');
        $candidate = $base;
        $suffix = 2;

        while (TenantUser::withTrashed()->where('username', $candidate)->exists()) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function generatePassword(): string
    {
        return Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);
    }

    private function emailDomain(): string
    {
        $tenantKey = tenancy()->tenant?->getTenantKey();

        return ($tenantKey ? Str::slug((string) $tenantKey) : 'lycee').'.local';
    }
}
