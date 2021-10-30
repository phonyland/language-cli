<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use LaravelZero\Framework\Commands\Command;
use MessagePack\MessagePack;
use Phonyland\LanguageModel\Model;
use Phonyland\NGram\TokenizerFilter;

class BuildModelCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'build
                            {path : Path of the text file}
                            {--name=Phony Language Model : A name for the model}
                            {--n=3 : N-Gram}
                            {--min-lenght=3 : The minimum length of the word considered in the generation of the model}
                            {--unique=false : Avoid skewing the generation toward the most repeated words in the text corpus}
                            {--exclude-originals=false : Blacklist original words from generation}
                            {--frequency-precision=7 : Frequency Precision}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Feed, build, and save the model';

    #[NoReturn]
    public function handle(): void
    {
        $model = new Model($this->option('name'));

        $this->task(($this->option('name') . ' Started'), function () use ($model) {
            $model->config->n((int) $this->option('n'))
                          ->minLenght((int) $this->option('min-lenght'))
                          ->unique(!($this->option('unique') === 'false'))
                          ->excludeOriginals(!($this->option('exclude-originals') === 'false'))
                          ->frequencyPrecision((int) $this->option('frequency-precision'))
                          ->tokenizer->addWordSeparatorPattern(TokenizerFilter::WHITESPACE_SEPARATOR)
                                     ->addWordFilterRule(TokenizerFilter::LATIN_EXTENDED_ALPHABETICAL)
                                     ->addSentenceSeparatorPattern(['.', '?', '!', ':', ';', '\n'])
                                     ->toLowercase();
        });

        $this->task('Feeding & Counting', function () use ($model) {
            $data = File::lines(getcwd().'/'.$this->argument('path'));

            $this->withProgressBar($data, function($line) use ($model) {
                $model->feed($line);
            });
        });

        $this->task('Calculating', function () use ($model) {
            $model->calculate();
        });

        $modelData = null;

        $this->task('Serializing', function() use (&$modelData, $model) {
            $modelData = serialize($model);
        });

        $this->task('Compressing', function() use (&$modelData) {
            $modelData = gzencode($modelData);
        });

        $filename = Str::snake($this->argument('path'));

        $this->task('Saving', function () use ($filename, &$modelData) {
            File::put(getcwd().'/' . $filename . '.phony', $modelData);
        });

        $this->info('Model saved as ' . $filename . '.phony');
    }
}
