<?php

namespace DavidRojo\SfTranslationHelper\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class TranslationsCopyCommand extends ContainerAwareCommand
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    private $helper;

    private $root;

    private $disableBackup = false;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('translation:copy')
            ->addArgument('from', InputArgument::REQUIRED, 'Origin language file')
            ->addArgument('to', InputArgument::REQUIRED, 'Destination language file')
            ->addArgument('file', InputArgument::REQUIRED, 'The full path from kernel_root to the input file')
            ->addArgument('addEmpty', InputArgument::OPTIONAL, 'Add empty values to the destination file', false)
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Should backup be disabled')
            ->setDescription('Hello PhpStorm')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates generates the
destination language messages file based on the origin language
file.

It finds non covered translations for each field of the original 
file and adds them to the destination file requesting the user the
translated value.

If user doesn't provide a translation the entity is not added to 
the destination unless "add_empty_translations" parameter is true.


  <info>php %command.full_name% en fr src/AppBundle/Resources/translations/messages.es.yml true|false</info>
  <info>php %command.full_name% en fr AppBundle true|false</info>

EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->helper = $this->getHelper('question');
        $this->input = $input;
        $this->output = $output;
        $this->root = $this->getContainer()->getParameter('kernel.root_dir');
        $this->disableBackup  = $input->getOption('no-backup') === true;

        $from       = $input->getArgument('from');
        $to         = $input->getArgument('to');
        $file       = $input->getArgument('file');
        $addEmpty   = $input->getArgument('addEmpty');


        if ($from == $to){
            $output->writeln("<error>Origin an destination language must be different.</error>");
            return;
        }

        // Generate file path if $file is a valid bundle name
        if ($this->bundleExists($file)) {
            $path = $this->getContainer()->get('kernel')->locateResource('@'.$file);
            $file = $path.'/Resources/translations/messages.'.$from.'.yml';
        }
        else{
            $file = $this->root.'/../'.$file;
        }

        // Check file exists
        if (!file_exists($file)){
            $output->writeln("<error>" . $file . " is not a bundle neither a file</error>");
            return;
        }

        // Create destination file path based on origin file
        $fromFilename = basename($file);
        $filename = substr(basename($file), 0, strlen($fromFilename) - strlen('.'.$from.'.yml'));
        $destFile = dirname($file).'/'.$filename.'.'.$to.'.yml';

        if (file_exists($destFile)){
            $r = $this->yesNo("Destination file already exists. Override? (y/n): ");
            if (!$r){
                return;
            }
        }

        $this->translateFile($file, $destFile, $addEmpty, $input, $output);
    }

    private function translateFile($from, $to, $addEmpty, InputInterface $input, OutputInterface $output){
        $fromYml = Yaml::parse(file_get_contents($from));

        if (file_exists($to)){
            $toYml = Yaml::parse(file_get_contents($to));

            // Just in case output file exist but is empty
            if ($toYml == null) {
                $toYml = [];
            }
        }
        else{
            $toYml = [];
        }

        $this->output->write('Counting missing translations...');
        list($countMissing, $countEmpty) = $this->countMissingTranslations($fromYml, $toYml);
        $this->output->writeln('<info>'.$countMissing.'</info> missing, <info>'.$countEmpty.'</info> empty');

        list($translations, $requests) = $this->recursiveTranslate($fromYml, $toYml, $addEmpty);

        if ($translations > 0) {
            $this->output->writeln("Saving file.");

            // Backup file if exists and backups are not disabled.
            if (file_exists($to) && !$this->disableBackup){
                copy($to, $to.'.backup');
            }

            file_put_contents($to, Yaml::dump($toYml, 4, 4));

            $this->output->writeln("File saved at <info>".substr($to, strlen($this->root))."</info>");
        }
        else{
            $this->output->writeln("No changes detected. <info>".$requests."</info> requests");
        }
    }

    private function recursiveTranslate($from, &$to, $addEmpty, $alreadyParsed = ""){
        $translations = 0; $requests = 0;
        foreach($from as $k => $v){
            if (is_array($v)){
                // Create array item if $to is empty
                if (!key_exists($k, $to)){
                    $to[$k] = [];
                }
                // Create array item if $to is a string instead of an array
                if (!is_array($to[$k])){
                    $to[$k] = [];
                }
                list($t, $req) = $this->recursiveTranslate($v, $to[$k], $addEmpty, $alreadyParsed.$k.'.');
                $translations += $t;
                $requests += $req;
            }
            else{
                if (!key_exists($k, $to)){
                    $requests++;
                    $value = $this->requestValue($alreadyParsed.$k, $v);
                    if ($value != "" || $addEmpty){
                        $to[$k] = $value;
                        $translations++;
                    }
                }
            }
        }
        return [$translations, $requests];
    }

    private function countMissingTranslations($from, $to){
        $countMissing = 0; $countEmpty = 0;
        foreach($from as $k => $v){
            if (is_array($v)){
                if ($to != null && key_exists($k, $to)){
                    // Avoid error if fromYml has a string insstead of an array
                    if (!is_array($to[$k])){
                        $to[$k] = [];
                    }
                    list($m, $e) = $this->countMissingTranslations($v, $to[$k]);
                }
                else{
                    list($m, $e) = $this->countMissingTranslations($v, null);
                }
                $countMissing += $m;
                $countEmpty += $e;
            }
            else{
                if ($to == null || !key_exists($k, $to)){
                    $countMissing++;
                }
                else{
                    if ($to[$k] == ""){
                        $countEmpty++;
                    }
                }
            }
        }
        return [$countMissing, $countEmpty];
    }

    /**
     * Sorts an array recursive by key
     * @param $array
     * @param int $sort_flags
     * @return bool
     */
    private function ksortRecursive(&$array, $sort_flags = SORT_REGULAR) {
        if (!is_array($array)) return false;
        ksort($array, $sort_flags);
        foreach ($array as &$arr) {
            $this->ksortRecursive($arr, $sort_flags);
        }
        return true;
    }

    /**
     * Check if a bundle exists.
     *
     * @param $bundle
     * @return boolC
     */
    private function bundleExists($bundle){
        return array_key_exists(
            $bundle,
            $this->getContainer()->getParameter('kernel.bundles')
        );
    }

    /**
     * Request a translation for $key
     *
     * @param $key
     * @param $from
     * @param null $current
     * @return string
     */
    private function requestValue($key, $from, $current =  null){

        $this->output->writeln("");
        $this->output->writeln("Field id: <info>".$key."</info>");
        $this->output->writeln("Original: <info>".$from."</info>");
        if ($current != ""){
            $this->output->writeln("Current value: <info>".$current."</info>");
        }

        $question = new Question('Please enter the translation: ', '');
        return $this->helper->ask($this->input, $this->output, $question);
    }

    private function yesNo($question, $default = false){
        $question = new ConfirmationQuestion($question, $default);
        $r = $this->helper->ask($this->input, $this->output, $question);
        return $r;
    }
}
