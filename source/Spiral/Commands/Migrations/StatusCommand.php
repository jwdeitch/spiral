<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright �2009-2015
 */
namespace Spiral\Commands\Migrations;

use Spiral\Commands\Migrations\Prototypes\AbstractCommand;
use Spiral\Database\Migrations\StatusInterface;

/**
 * Show all available migrations and their statuses.
 */
class StatusCommand extends AbstractCommand
{
    /**
     * Text to show if migration is not performed.
     */
    const PENDING = '<fg=red>not executed yet</fg=red>';

    /**
     * {@inheritdoc}
     */
    protected $name = 'migrate:status';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Get list of all available migrations and their statuses.';

    /**
     * Perform command.
     */
    public function perform()
    {
        if (!$this->migrator()->isConfigured()) {
            $this->writeln(
                "<fg=red>Migrations does not configured yet, run '<info>migrate:init</info>' first.</fg=red>"
            );

            return;
        }

        if (empty($this->migrator()->getMigrations())) {
            $this->writeln("No migrations were found.");

            return;
        }

        $table = $this->tableHelper(['Migration:', 'Filename:', 'Created at', 'Performed at']);
        foreach ($this->migrator()->getMigrations() as $migration) {
            $filename = (new \ReflectionClass($migration))->getFileName();

            $table->addRow([
                $migration->getStatus()->getName(),
                $this->files->relativePath($filename, $this->migrator()->config()['directory']),
                $migration->getStatus()->getTimeCreated()->format('Y-m-d H:i:s'),
                $migration->getStatus()->getState() == StatusInterface::PENDING
                    ? self::PENDING
                    : $migration->getStatus()->getTimeExecuted()->format('Y-m-d H:i:s')
            ]);
        }

        $table->render();
    }
}