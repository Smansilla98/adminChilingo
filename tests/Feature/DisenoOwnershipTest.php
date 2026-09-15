<?php

namespace Tests\Feature;

use App\Models\Diseno;
use App\Models\User;
use App\Policies\DisenoPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BusinessSchema;
use Tests\TestCase;

class DisenoOwnershipTest extends TestCase
{
    private DisenoPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Requiere extensión pdo_sqlite.');
        }

        BusinessSchema::migrateMinimal();
        Schema::dropIfExists('disenos');
        Schema::create('disenos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('formato', 40)->default('flyer_feed');
            $table->unsignedInteger('ancho')->default(1080);
            $table->unsignedInteger('alto')->default(1350);
            $table->json('canvas_json')->nullable();
            $table->string('preview_path')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->policy = new DisenoPolicy;
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin Diseño',
            'username' => 'admindiseno',
            'email' => 'admindiseno@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_edita_y_borra_cualquier_diseno(): void
    {
        $admin = $this->admin();
        $otro = User::create([
            'name' => 'Otro',
            'username' => 'otrodiseno',
            'email' => 'otro@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $otro->assignRole('admin');

        $diseno = Diseno::create([
            'titulo' => 'Ajeno',
            'formato' => 'flyer_feed',
            'ancho' => 1080,
            'alto' => 1350,
            'canvas_json' => ['version' => '6', 'objects' => []],
            'user_id' => $otro->id,
        ]);

        $this->assertTrue($this->policy->update($admin, $diseno));
        $this->assertTrue($this->policy->delete($admin, $diseno));

        $this->actingAs($admin)
            ->delete(route('disenos.destroy', $diseno))
            ->assertRedirect(route('disenos.index'));

        $this->assertDatabaseMissing('disenos', ['id' => $diseno->id]);
    }

    public function test_subir_medio_guarda_en_storage_y_devuelve_url(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $file = UploadedFile::fake()->image('logo-chilinga.png', 200, 120);

        $res = $this->actingAs($admin)
            ->postJson(route('disenos.medios.store'), ['archivo' => $file]);

        $res->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['url', 'path', 'name']);

        Storage::disk('public')->assertExists($res->json('path'));
        $this->assertStringContainsString('/storage/disenos/assets/', $res->json('url'));
    }

    public function test_index_lista_disenos_para_admin(): void
    {
        $admin = $this->admin();
        Diseno::create([
            'titulo' => 'Flyer test',
            'formato' => 'flyer_feed',
            'ancho' => 1080,
            'alto' => 1350,
            'canvas_json' => ['objects' => []],
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('disenos.index'))
            ->assertOk()
            ->assertSee('Flyer test')
            ->assertSee('Eliminar');
    }

    public function test_api_biblioteca_items_para_editor(): void
    {
        $admin = $this->admin();

        // Sin tabla de biblioteca → respuesta vacía ok
        $this->actingAs($admin)
            ->getJson(route('disenos.biblioteca.items'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data', 'meta', 'tags']);
    }

    public function test_kit_solo_admin_puede_subir_y_borrar(): void
    {
        Storage::fake('public');

        Schema::dropIfExists('diseno_kit_assets');
        Schema::create('diseno_kit_assets', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 120);
            $table->string('path');
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $admin = $this->admin();
        $file = UploadedFile::fake()->image('kit-logo.png', 120, 120);

        $res = $this->actingAs($admin)
            ->postJson(route('disenos.kit.store'), [
                'archivo' => $file,
                'titulo' => 'Logo kit',
            ]);

        $res->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('item.label', 'Logo kit');

        $kitId = (int) $res->json('item.kit_id');
        $this->assertGreaterThan(0, $kitId);
        $this->assertDatabaseHas('diseno_kit_assets', ['id' => $kitId, 'titulo' => 'Logo kit']);

        $path = \App\Models\DisenoKitAsset::query()->find($kitId)?->path;
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)
            ->deleteJson(route('disenos.kit.destroy', ['kit' => $kitId]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('diseno_kit_assets', ['id' => $kitId]);
    }

    public function test_kit_policy_solo_admin_o_direccion(): void
    {
        $admin = $this->admin();
        $this->assertTrue($this->policy->manageKit($admin));

        $profesor = User::create([
            'name' => 'Profe Diseño',
            'username' => 'profediseno',
            'email' => 'profediseno@test.local',
            'password' => Hash::make('password'),
            'role' => 'profesor',
            'modulos_access' => ['admin.disenos' => true],
        ]);
        \Spatie\Permission\Models\Role::findOrCreate('profesor', 'web');
        $profesor->assignRole('profesor');

        $this->assertTrue($profesor->tieneAccesoModulo('admin.disenos'));
        $this->assertFalse($this->policy->manageKit($profesor));
        $this->assertTrue($this->policy->uploadAsset($profesor));
    }

    public function test_view_permite_plantilla_estudio_sin_editar(): void
    {
        $profesor = User::create([
            'name' => 'Profe Vista',
            'username' => 'profevista',
            'email' => 'profevista@test.local',
            'password' => Hash::make('password'),
            'role' => 'profesor',
            'modulos_access' => ['admin.disenos' => true],
        ]);
        \Spatie\Permission\Models\Role::findOrCreate('profesor', 'web');
        $profesor->assignRole('profesor');

        $plantilla = Diseno::create([
            'titulo' => 'Plantilla estudio',
            'formato' => 'flyer_feed',
            'ancho' => 1080,
            'alto' => 1350,
            'canvas_json' => ['objects' => []],
            'user_id' => null,
        ]);

        $this->assertTrue($this->policy->view($profesor, $plantilla));
        $this->assertFalse($this->policy->update($profesor, $plantilla));
        $this->assertFalse($this->policy->delete($profesor, $plantilla));
    }
}
