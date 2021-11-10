<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use LaravelZero\Framework\Commands\Command;
use MessagePack\MessagePack;
use Phonyland\LanguageModel\Model;
use Phonyland\NGram\Tokenizer;
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
                            {-A=Phony Language Model : A name for the model}
                            {-N=3 : N-Gram Lenght}
                            {-M=3 : The minimum length of the word considered}
                            {-U=false : Avoid skewing the generation toward the most repeated words in the text corpus}
                            {-E=false : Exclude Originals}
                            {-P=7 : Frequency Precision}
                            {-S=5 : Number of Sentence Elements}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generate a Phony Language Model';

    #[NoReturn]
    public function handle(): void
    {
        $model = new Model($this->argument('-A'));

        $this->task(($this->argument('-A').' Started'), function () use ($model) {
            $model->config->n((int) $this->argument('-N'))
                          ->minLenght((int) $this->argument('-M'))
                          ->unique(!($this->argument('-U') === 'false'))
                          ->excludeOriginals(!($this->argument('-E') === 'false'))
                          ->frequencyPrecision((int) $this->argument('-P'))
                          ->numberOfSentenceElements((int) $this->argument('-S'))
                          ->tokenizer((new Tokenizer())
                              ->addWordSeparatorPattern(TokenizerFilter::WHITESPACE_SEPARATOR)
                              ->addWordFilterRule(TokenizerFilter::LATIN_EXTENDED_ALPHABETICAL)
                              ->addSentenceSeparatorPattern(['.', '?', '!', ':', ';', '\n'])
                              ->toLowercase()
                          );
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
