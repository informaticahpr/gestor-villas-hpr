<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => User::with('role')->orderBy('name')->get()->map(fn (User $u) => $this->serializar($u)),
            'roles' => Role::pluck('nombre'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('users', 'name')->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', 'string', 'exists:roles,nombre'],
        ], [
            'name.required' => 'Debes indicar el nombre del usuario.',
            'name.max' => 'El nombre no puede superar los 100 caracteres.',
            'name.unique' => 'Ya existe un usuario con ese nombre. Se usa para iniciar sesión.',
            'email.required' => 'Debes indicar el correo electrónico.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe un usuario registrado con ese correo.',
            'password.required' => 'Debes indicar una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'rol.required' => 'Debes seleccionar un rol.',
            'rol.exists' => 'El rol seleccionado no es válido.',
        ]);

        $rolId = Role::where('nombre', $data['rol'])->value('id');

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'rol_id' => $rolId,
            'activo' => true,
        ]);

        Bitacora::registrar('usuario', 'crear', "Creó al usuario \"{$user->name}\" ({$data['rol']}).");

        return response()->json(['user' => $this->serializar($user)], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('users', 'name')->ignore($user->id)->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'rol' => ['required', 'string', 'exists:roles,nombre'],
        ], [
            'name.required' => 'Debes indicar el nombre del usuario.',
            'name.max' => 'El nombre no puede superar los 100 caracteres.',
            'name.unique' => 'Ya existe un usuario con ese nombre. Se usa para iniciar sesión.',
            'email.required' => 'Debes indicar el correo electrónico.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe un usuario registrado con ese correo.',
            'rol.required' => 'Debes seleccionar un rol.',
            'rol.exists' => 'El rol seleccionado no es válido.',
        ]);

        $rolId = Role::where('nombre', $data['rol'])->value('id');

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'rol_id' => $rolId,
        ]);

        Bitacora::registrar('usuario', 'editar', "Editó al usuario \"{$user->name}\".");

        return response()->json(['user' => $this->serializar($user)]);
    }

    public function cambiarPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ], [
            'password.required' => 'Debes indicar la nueva contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $user->update(['password' => $data['password']]);

        Bitacora::registrar('usuario', 'editar', "Cambió la contraseña del usuario \"{$user->name}\".");

        return response()->json(status: 204);
    }

    public function activar(Request $request, User $user)
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        if (! $data['activo'] && $user->id === $request->user()->id) {
            return response()->json([
                'message' => 'No puedes deshabilitar tu propia cuenta.',
            ], 422);
        }

        $user->update(['activo' => $data['activo']]);

        Bitacora::registrar('usuario', $data['activo'] ? 'activar' : 'desactivar', ($data['activo'] ? 'Habilitó' : 'Deshabilitó')." al usuario \"{$user->name}\".");

        return response()->json(['user' => $this->serializar($user)]);
    }

    /**
     * "Eliminar" un usuario nunca borra la fila -- hace soft delete (queda
     * deleted_at, la fila sigue en la BD para conservar el historial de
     * movimientos y de bitacora). Solo se puede eliminar si ya esta
     * deshabilitado, como salvaguarda extra ante un borrado accidental.
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->activo) {
            return response()->json([
                'message' => 'Solo se pueden eliminar usuarios deshabilitados. Deshabilítalo primero.',
            ], 422);
        }

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta.',
            ], 422);
        }

        $nombre = $user->name;
        $user->delete();

        Bitacora::registrar('usuario', 'eliminar', "Eliminó al usuario \"{$nombre}\".");

        return response()->json(status: 204);
    }

    private function serializar(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $user->role?->nombre,
            'activo' => (bool) $user->activo,
        ];
    }
}
