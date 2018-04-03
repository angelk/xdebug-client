<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author po_taka
 */
class RunCommand extends Command
{
    const COMMANT_NUMBER_START = 1;
    private $commandNumber = self::COMMANT_NUMBER_START;

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setName('app:start');

        $this->addOption('port', 'p', InputOption::VALUE_OPTIONAL,'port to listen', 9000);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $address = '127.0.0.1';
        $port = $input->getOption('port');
        $output->writeln('Using port ' . $port);

        $symfonyStyle = new SymfonyStyle($input, $output);

        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            $output->writeln('<error>socket_create() failed: reason: ' . socket_strerror(socket_last_error()));
            return -1;
        }

        if (socket_bind($sock, $address, $port) === false) {
            $output->writeln("<error>socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "</error>");

            return -1;
        }

        if (socket_listen($sock, 0) === false) {
            $output->writeln("<error>socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "</error>");

            return -1;
        }

        do {
            $output->writeln("<info>Waiting for connection...</info>");
            if (($msgsock = socket_accept($sock)) === false) {
                $output->writeln("<warning>socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)) . "</warning>");
                break;
            }

            do {
                $output->writeln("Reading response...");
                if (false === ($buf = socket_read($msgsock, 2048, PHP_BINARY_READ))) {
                    $symfonyStyle->error("socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)));
                    break 2;
                }

                if (!$buf = trim($buf)) {
                    $symfonyStyle->warning('Received empty response, closing socket!');
                    break;
                }

                // @FIXME
                $response = preg_replace('/.*?(\<\?xml)/s', '$1', $buf);
                $output->writeln('parsed: ' . $response, OutputInterface::VERBOSITY_VERY_VERBOSE);

                // @parse xml!!! @FIXME

                $dom = new \Symfony\Component\DomCrawler\Crawler($response);
                $xdebugMessage = $dom->filterXPath('//default:response/xdebug:message');

                if (count($xdebugMessage)) {
                    var_dump($xdebugMessage->attr('filename'));
                    var_dump($xdebugMessage->attr('lineno'));
                }

                $questionHelper = $this->getHelper('question');
                /* @var $questionHelper \Symfony\Component\Console\Helper\QuestionHelper */
                $question = new Question('Command:', 'step_over');
                $question->setAutocompleterValues(
                    [
                        'run',
                        'step_over',
                        'step_into',
                    ]
                );
                $command = $questionHelper->ask(
                    $input,
                    $output, 
                    $question
                );

                $this->sendCommand($output, $msgsock, $command);
            } while (true);
            $this->commandNumber = self::COMMANT_NUMBER_START;
            socket_close($msgsock);
        } while (true);

        socket_close($sock);
    }

    protected function sendCommand(OutputInterface $outout, $msgsock, string $command)
    {
        $streamToWrite = $command . ' -i ' . $this->commandNumber;
        $this->commandNumber++;
                
        $outout->writeln('Sending `' . $streamToWrite . '`', OutputInterface::VERBOSITY_VERY_VERBOSE);

        socket_write($msgsock, $streamToWrite, strlen($streamToWrite));
        socket_write($msgsock, "\0", 1);
    }
}
