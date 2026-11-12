<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class RespaldoController extends Controller
{
    // Pantalla con la explicación y el botón de descarga
    public function index()
    {
        return view('respaldo.index');
    }

    // Descarga una copia completa de la base de datos (archivo .sqlite)
    public function descargar()
    {
        if (DB::getDriverName() !== 'sqlite') {
            return back()->withErrors(['respaldo' => 'El respaldo desde esta pantalla solo funciona con SQLite. Con MySQL se usa el respaldo del hosting.']);
        }

        $origen = (string) config('database.connections.sqlite.database');
        $origen = realpath($origen) ?: (realpath(base_path($origen)) ?: $origen);

        if ($origen === '' || $origen === ':memory:' || ! is_file($origen)) {
            return back()->withErrors(['respaldo' => 'No se encontró el archivo de la base de datos para respaldar.']);
        }

        // Asegura que todo lo pendiente quede escrito en el archivo antes de copiarlo
        try {
            DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (Throwable $e) {
            // Si la base no usa ese modo, no pasa nada
        }

        $carpeta = storage_path('app/respaldos');
        File::ensureDirectoryExists($carpeta);

        $nombre = 'respaldo-lecciones-'.now()->format('Y-m-d_His').'.sqlite';
        $destino = $carpeta.DIRECTORY_SEPARATOR.$nombre;

        copy($origen, $destino);

        // Se entrega al navegador y se borra la copia temporal del servidor
        return response()->download($destino, $nombre)->deleteFileAfterSend(true);
    }
}
