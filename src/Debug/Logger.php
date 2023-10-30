<?php

namespace Parapente\Synacor\Debug;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use Bramus\Ansi\Writers\StreamWriter;
use DateTime;
use DateTimeInterface;

class Logger
{
	private $output;
	private StreamWriter $streamWriter;
	private bool $running = false;

	public function __construct(string $filename)
	{
		echo "Logging to file '$filename'\n";
		$this->output = fopen($filename, "a");

		if (!$this->output)
			throw new \Exception("Cannot open file '$filename' for writing the log.");
		
		$this->streamWriter = new StreamWriter($this->output);
	}

	public function start(): void
	{
		if ($this->running)
			return;

		$timestamp = (new DateTime())->format(DateTimeInterface::RFC822);
		if (!fwrite($this->output, "$timestamp - Logging started\n"))
			throw new \Exception("Cannot write to log!");

		$this->running = true;
	}

	public function stop(): void
	{
		if (!$this->running)
			return;

		$timestamp = (new DateTime())->format(DateTimeInterface::RFC822);
		if (!fwrite($this->output, "$timestamp - Logging stopped\n"))
			throw new \Exception("Cannot write to log!");

		$this->running = false;
	}

	public function isRunning(): bool
	{
		return $this->running;
	}

	public function log(int $pc, string $op, array $arguments = [], array $values = []): void
	{
		if (!$this->running)
			return;

		$ansi = new Ansi($this->streamWriter);
		$ansi->text(sprintf("PC: %5s -- ", $pc))
			->bold()
			->text(sprintf("%4s ", $op))
			->normal()
			->color(SGR::COLOR_FG_YELLOW)
			->text(
				array_reduce(
					$arguments, 
					fn($carry, $item) => $carry ? "$carry, $item" : "$item"
				)
			)
			->reset();
		
		if ($values)
			$ansi->text(" -- ")
				->color(SGR::COLOR_FG_BLUE_BRIGHT)
				->text(
					array_reduce(
						$values, 
						fn($carry, $item) => $carry ? "$carry, $item" : "$item"
					)
				)
				->reset();

		$ansi->lf();
	}

	/**
	 * Transform address to register name if needed
	 * @param int $address 
	 * @return string 
	 */
	public function translate(int $address) : string {
		if ($address >= 32768 && $address <= 32775)
			return "R" . ($address - 32768);
		
		return "$address";
	}
}