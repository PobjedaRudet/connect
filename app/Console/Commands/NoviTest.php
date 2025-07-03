<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NoviTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:novi-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Komanda za testiranje';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //Komanda za testiranje
        $this->info('Novi test komanda je pokrenuta.');
        // Ovdje možete dodati logiku koju želite testirati
        // Na primjer, možete slati mail ili izvršiti neku drugu operaciju
        Mail::to('h.ahmet@pobjeda.com')->send(new \App\Mail\UpcomingExamsMail([], []));
        $this->info('Test komanda je uspješno izvršena.');
        // Možete dodati više informacija ili logike prema potrebi
        // Na primjer, možete koristiti Log::info() za zapisivanje u logove
        Log::info('Novi test komanda je uspješno izvršena.');
        // Ili možete koristiti dd() za ispisivanje rezultata
    }
}
