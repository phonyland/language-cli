<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

    public function handle(): void
    {
        $model = new Model($this->option('name'));

        $this->task((string) ($this->option('name')), function () use ($model) {
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

        $this->task('Feeding', function () use ($model) {
            $data = File::lines(getcwd().'/'.$this->argument('path'));

            foreach ($data as $index => $line) {
                $model->feed($line);
            }
        });

        $this->task('Calculating', function () use ($model) {
            $model->calculate();
        });

        $filename = Str::snake($this->option('name'));

        $this->task('Encoding & Compressing', function () use ($filename, $model) {

            File::put(getcwd().'/' . $filename . '.phony', gzencode(MessagePack::pack($model->build())));
        });

        $this->info('Model saved as ' . $filename . '.phony');
    }
}
