<?php

namespace Database\Seeders;

use App\Models\Docente;
use Illuminate\Database\Seeder;

class DocenteSeeder extends Seeder
{
    public function run(): void
    {
        $nombres = [
            'Ana Lucía Mora Jiménez',
            'Daniela Castro Solís',
            'María José Vargas Rojas',
            'Laura Quesada Brenes',
            'Sofía Herrera Calvo',
            'Valeria Segura Pacheco',
            'Carolina Méndez Ulate',
            'Paola Salas Arce',
            'Natalia Brenes Soto',
            'Gabriela Alfaro Vega',
            'Adriana León Esquivel',
            'Mariana Picado Zúñiga',
            'Elena Campos Barrantes',
            'Silvia Arguedas Lobo',
            'Patricia Umaña Robles',
            'Andrea Montoya Chacón',
            'Lucía Badilla Navarro',
            'Diego Granados Alpízar',
            'Fernanda Ruiz Madrigal',
            'Isabel Porras Aguilar',
            'Verónica Sibaja Durán',
            'Camila Torres Villegas',
            'Mónica Bonilla Ortiz',
            'Tamara Céspedes Fonseca',
        ];

        foreach ($nombres as $nombre) {
            Docente::firstOrCreate(['nombre' => $nombre]);
        }
    }
}