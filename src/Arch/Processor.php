<?php 

namespace Parapente\Synacor\Arch;

use Ds\Queue;
use Ds\Stack;
use Exception;
use Parapente\Synacor\Debug\Logger;

class Processor
{
	private $ops = [
		"halt", // 0
		"set", // 1
		"push", // 2
		"pop", // 3
		"eq", // 4
		"gt", // 5
		"jmp", // 6
		"jt", // 7
		"jf", // 8
		"add", // 9
		"mult", // 10
		"mod", // 11
		"and", // 12
		"or", // 13
		"not", // 14
		"rmem", // 15
		"wmem", // 16
		"call", // 17
		"ret", // 18
		"out", // 19
		"in", // 20
		"noop" // 21
	];

	private Memory $mem;
	private Stack $stack;
	private array $register;
	private int $pc;
	private bool $halted;
	private Queue $inputBuffer;
	public Logger $logger;

	public function __construct(Memory $memory)
	{
		$this->pc = 0;
		$this->halted = false;
		$this->mem = $memory;
		$this->stack = new Stack();
		$this->register = array_fill(0, 8, 0);
		$this->inputBuffer = new Queue();
		$this->logger = new Logger(__DIR__ . "/../../debug.log");
	}

	public function start(): void
	{
		$this->halted = false;
		while (!$this->halted) {
			$this->nextStep();
		}
	}

	private function nextStep(): void
	{
		$nextOpCode = $this->mem->getAddr($this->pc);
		if ($nextOpCode > 21) {
			throw new \Exception("Invalid op code $nextOpCode");
		}

		$this->{$this->ops[$nextOpCode]}();
	}

	public function readFrom(int $from): int
	{
		if (0 < $from && $from < 32768) {
			return $this->mem->getAddr($from);
		} else if ($from >= 32768 && $from <= 32775) {
			return $this->register[$from - 32768];
		} else {
			throw new \Exception("Invalid reading address ($from)");
		}
	}

	public function writeTo(int $to, int $value): void
	{
		if (0 < $to && $to < 32768) {
			$this->mem->setAddr($to, $value);
		} else if ($to >= 32768 && $to <= 32775) {
			$this->register[$to - 32768] = $value;
		} else {
			throw new \Exception("Invalid writing address ($to)");
		}
	}

	public function addToInputBuffer(string $text)
	{
		$chars = array_map(
			fn($item) => ord($item), 
			str_split("$text\n")
		);

		foreach ($chars as $char) {
			$this->inputBuffer->push($char);
		}
	}

	/**
	 * Check if memory address is valid
	 * @param int $address The memory address to check
	 * @return void 
	 * @throws Exception 
	 */
	private function checkAddress(int $address): void
	{
		if ($address < 0 || $address > 32767) {
			throw new \Exception("Invalid memory address ($address)");
		}
	}

	/**
	 * Check if the number is a literal value or a register value
	 * @param int $number 
	 * @return int The literal value or the value of the register
	 * @throws Exception when the number is invalid
	 */
	private function checkNumber(int $number): int
	{
		// See if we should check a register
		if ($number >= 32768 && $number <= 32775) {
			return $this->register[$number - 32768];
		}

		if ($number < 0 || $number > 32775) {
			throw new \Exception("Invalid number ($number)!");
		}

		return $number;
	}

	private function halt(): void
	{
		$this->logger->log($this->pc, "halt");
		$this->halted = true;
	}

	private function set(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$registerA = $a - 32768;

		if ($registerA < 0 || $registerA > 7) {
			throw new \Exception("Invalid value a in set ($registerA)");
		}

		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);

		$this->logger->log($this->pc, "set", [
			$this->logger->translate($a),
			$this->logger->translate($b),
		], ["$valueA", "$valueB"]);

		$this->writeTo($a, $valueB);

		$this->pc += 3;
	}

	private function push(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);

		$this->logger->log($this->pc, "push", [
			$this->logger->translate($a),
		], ["$valueA"]);

		$this->stack->push($valueA);

		$this->pc += 2;
	}

	private function pop(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);

		$value = $this->stack->pop();

		$this->logger->log($this->pc, "pop", [
			$this->logger->translate($a),
		], ["$valueA"]);

		$this->writeTo($a, $value);
		$this->pc += 2;
	}

	private function eq(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$valueC = $this->checkNumber($c);

		$this->logger->log($this->pc, "eq", [
			$this->logger->translate($a),
			$this->logger->translate($b),
			$this->logger->translate($c)
		], ["$valueA", "$valueB", "$valueC"]);

		if ($valueB === $valueC) {
			$this->writeTo($a, 1);
		} else {
			$this->writeTo($a, 0);
		}

		$this->pc += 4;
	}

	private function gt(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$valueC = $this->checkNumber($c);

		$this->logger->log($this->pc, "gt", [
			$this->logger->translate($a),
			$this->logger->translate($b),
			$this->logger->translate($c)
		], ["$valueA", "$valueB", "$valueC"]);

		if ($valueB > $valueC) {
			$this->writeTo($a, 1);
		} else {
			$this->writeTo($a, 0);
		}

		$this->pc += 4;		
	}

	private function jmp(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);

		$this->logger->log($this->pc, "jmp", [
			$this->logger->translate($a),
		], ["$valueA"]);

		$this->checkAddress($valueA);

		$this->pc = $valueA;
	}

	private function jt(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);

		$this->checkAddress($b);
		$valueA = $this->checkNumber($a);
		$valueB = $this->checkNumber($b);

		$this->logger->log($this->pc, "jt", [
			$this->logger->translate($a),
			$this->logger->translate($b),
		], ["$valueA", "$valueB"]);

		if ($valueA !== 0) {
			$this->pc = $valueB;
		} else {
			$this->pc += 3;
		}	
	}

	private function jf(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);

		$this->checkAddress($b);
		$valueA = $this->checkNumber($a);
		$valueB = $this->checkNumber($b);

		$this->logger->log($this->pc, "jf", [
			$this->logger->translate($a),
			$this->logger->translate($b),
		], ["$valueA", "$valueB"]);

		if ($valueA === 0) {
			$this->pc = $valueB;
		} else {
			$this->pc += 3;
		}	
	}

	private function add(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$valueC = $this->checkNumber($c);

		$this->logger->log($this->pc, "add", [
			$this->logger->translate($a),
			$this->logger->translate($b),
			$this->logger->translate($c)
		], ["$valueA", "$valueB", "$valueC"]);

		$this->writeTo($a, ($valueB + $valueC) % 32768);

		$this->pc += 4;		
	}

	private function mult(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$valueC = $this->checkNumber($c);

		$this->logger->log($this->pc, "mult", [
			$this->logger->translate($a),
			$this->logger->translate($b),
			$this->logger->translate($c)
		], ["$valueA", "$valueB", "$valueC"]);

		$this->writeTo($a, ($valueB * $valueC) % 32768);

		$this->pc += 4;		
	}

	private function mod(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$valueC = $this->checkNumber($c);

		$this->logger->log($this->pc, "mod", [
			$this->logger->translate($a),
			$this->logger->translate($b),
			$this->logger->translate($c)
		], ["$valueA", "$valueB", "$valueC"]);

		$this->writeTo($a, $valueB % $valueC);

		$this->pc += 4;		
	}

	private function and(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$valueC = $this->checkNumber($c);

		$this->logger->log($this->pc, "and", [
			$this->logger->translate($a),
			$this->logger->translate($b),
			$this->logger->translate($c)
		], ["$valueA", "$valueB", "$valueC"]);

		$this->writeTo($a, $valueB & $valueC);

		$this->pc += 4;		
	}

	private function or(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$valueC = $this->checkNumber($c);

		$this->logger->log($this->pc, "or", [
			$this->logger->translate($a),
			$this->logger->translate($b),
			$this->logger->translate($c)
		], ["$valueA", "$valueB", "$valueC"]);

		$this->writeTo($a, $valueB | $valueC);

		$this->pc += 4;
	}

	private function not(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);

		$this->logger->log($this->pc, "not", [
			$this->logger->translate($a),
			$this->logger->translate($b)
		], ["$valueA", "$valueB"]);

		$this->writeTo($a, 32767 ^ $valueB);

		$this->pc += 3;
	}

	private function rmem(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);
		$value = $this->readFrom($valueB);

		$this->logger->log($this->pc, "rmem", [
			$this->logger->translate($a),
			$this->logger->translate($b)
		], ["$valueA", "$valueB -> $value"]);

		$this->writeTo($a, $value);

		$this->pc += 3;
	}

	private function wmem(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$valueA = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$valueB = $this->checkNumber($b);

		$this->logger->log($this->pc, "wmem", [
			$this->logger->translate($a),
			$this->logger->translate($b)
		], ["$valueA", "$valueB"]);

		$this->writeTo($valueA, $valueB);

		$this->pc += 3;
	}

	private function call(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$value = $this->checkNumber($a);

		$this->checkAddress($value);

		$this->logger->log($this->pc, "call", [
			$this->logger->translate($a)
		], ["$value"]);

		$this->stack->push($this->pc + 2);
		$this->pc = $value;
	}

	private function ret(): void
	{
		try {
			$address = $this->stack->pop();
		} catch (\UnderflowException $e) {
			$this->halt();
			return;
		}

		$this->logger->log($this->pc, "ret");

		$this->checkAddress($address);

		$this->pc = $address;
	}

	private function out(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$value = $this->checkNumber($a);

		$this->logger->log($this->pc, "out", [
			$this->logger->translate($a)
		], ["$value"]);

		echo chr($value);

		$this->pc += 2;
	}

	private function in(): void
	{
		$address = $this->pc + 1;
		$a = $this->readFrom($address);

		$this->logger->log($this->pc, "in", [
			$this->logger->translate($a)
		]);

		if ($this->inputBuffer->isEmpty()) {
			$proceed = false;
			while (!$proceed) {
				$input = readline('(? for help) > ');
	
				if ($input === "?") {
					echo "help           - In game help\n";
					echo "\nThe following commands are useful for the last\n";
					echo "part of the game.\n";
					echo "r              - Print the cpu registers\n";
					echo "r7=<num>       - Set the value of register 7\n";
					echo "bypass         - Bypass the confirmation mechanism\n";
					echo "debug          - Show debug status\n\n";
					echo "debug=<on|off> - Toggle debug info logging\n\n";
				} else if ($input === "r") {
					for ($i = 0; $i < 8; $i++) {
						echo "R$i = {$this->register[$i]}\n";
					}
				} else if (str_starts_with($input, "r7=")) {
					$value = str_replace("r7=", "", $input);
					$this->register[7] = intval($value);
				} else if ($input === "b") {
					// TODO: bypass
				} else if ($input === "debug") {
					echo "Debug: " . $this->logger->isRunning() ? "on" : "off" . "\n";
				} else if (str_starts_with($input, "debug=")) {
					$value = str_replace("debug=", "", $input);

					if ($value === "on") {
						$this->logger->start();
						echo "Setting debug to on.\n";
					}

					if ($value === "off") {
						$this->logger->stop();
						echo "Setting debug to off.\n";
					}
				} else {
					$proceed = true;
				}
			}
			$this->addToInputBuffer($input);
		}

		$this->writeTo($a, $this->inputBuffer->pop());

		$this->pc += 2;
	}

	private function noop(): void
	{
		$this->logger->log($this->pc, "noop");

		$this->pc++;
	}
}