<?php 

namespace Parapente\Synacor\Arch;

use Ds\Queue;
use Ds\Stack;
use Exception;

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
	public bool $debug;

	public function __construct(Memory $memory)
	{
		$this->pc = 0;
		$this->halted = false;
		$this->mem = $memory;
		$this->stack = new Stack();
		$this->register = array_fill(0, 8, 0);
		$this->inputBuffer = new Queue();
		$this->debug = false;
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
		if ($this->debug) {
			echo "PC: $this->pc - halt\n";
		}

		$this->halted = true;
	}

	private function set(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - set\n";
		}

		$address = $this->pc + 1;
		$to = $this->mem->getAddr($address);
		$registerA = $to - 32768;

		if ($registerA < 0 || $registerA > 7) {
			throw new \Exception("Invalid value a in set ($registerA)");
		}

		$value = $this->mem->getAddr($address + 1);
		$value = $this->checkNumber($value);
		$this->writeTo($to, $value);

		$this->pc += 3;
	}

	private function push(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - push\n";
		}

		$address = $this->pc + 1;
		$value = $this->mem->getAddr($address);
		$value = $this->checkNumber($value);
		if ($this->debug) {
			echo "$value \n";
		}
		$this->stack->push($value);

		$this->pc += 2;
	}

	private function pop(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - pop\n";
		}

		$address = $this->pc + 1;
		$to = $this->mem->getAddr($address);
		$value = $this->stack->pop();

		$this->writeTo($to, $value);
		$this->pc += 2;
	}

	private function eq(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - eq\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$c = $this->checkNumber($c);

		if ($this->debug) {
			echo "$a,$b,$c\n";
		}

		if ($b === $c) {
			$this->writeTo($a, 1);
		} else {
			$this->writeTo($a, 0);
		}

		$this->pc += 4;
	}

	private function gt(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - gt\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$c = $this->checkNumber($c);

		if ($b > $c) {
			$this->writeTo($a, 1);
		} else {
			$this->writeTo($a, 0);
		}

		$this->pc += 4;		
	}

	private function jmp(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - jmp\n";
		}

		$address = $this->pc + 1;
		$newAddress = $this->readFrom($address);
		$newAddress = $this->checkNumber($newAddress);

		$this->checkAddress($newAddress);

		$this->pc = $newAddress;
	}

	private function jt(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - jt\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);

		if ($this->debug) {
			echo "a: $a\n";
			echo "b: $b\n";
		}

		$this->checkAddress($b);
		$a = $this->checkNumber($a);
		$b = $this->checkNumber($b);

		if ($a !== 0) {
			$this->pc = $b;
		} else {
			$this->pc += 3;
		}	
	}

	private function jf(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - jf\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);

		$this->checkAddress($b);
		$a = $this->checkNumber($a);
		$b = $this->checkNumber($b);

		if ($this->debug) {
			echo "$a,$b\n";
		}

		if ($a === 0) {
			$this->pc = $b;
		} else {
			$this->pc += 3;
		}	
	}

	private function add(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - add\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$c = $this->checkNumber($c);

		if ($this->debug) {
			echo "$a = $b + $c\n";
		}

		$this->writeTo($a, ($b + $c) % 32768);

		$this->pc += 4;		
	}

	private function mult(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - mult\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$c = $this->checkNumber($c);

		$this->writeTo($a, ($b * $c) % 32768);

		$this->pc += 4;		
	}

	private function mod(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - mod\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$c = $this->checkNumber($c);

		$this->writeTo($a, $b % $c);

		$this->pc += 4;		
	}

	private function and(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - and\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$c = $this->checkNumber($c);

		$this->writeTo($a, $b & $c);

		$this->pc += 4;		
	}

	private function or(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - or\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);
		$c = $this->readFrom($address + 2);
		$c = $this->checkNumber($c);

		$this->writeTo($a, $b | $c);

		$this->pc += 4;
	}

	private function not(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - not\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);

		$this->writeTo($a, 32767 ^ $b);

		$this->pc += 3;
	}

	private function rmem(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - rmem\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$b = $this->readFrom($address + 1);
		$b = $this->checkNumber($b);
		$value = $this->readFrom($b);
		// $value = $b;

		if ($this->debug) {
			echo "$a, $b, $value \n";
		}

		$this->writeTo($a, $value);

		$this->pc += 3;
	}

	private function wmem(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - wmem\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$a = $this->checkNumber($a);
		$b = $this->readFrom($address + 1);
		$value = $this->checkNumber($b);

		$this->writeTo($a, $value);

		$this->pc += 3;
	}

	private function call(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - call\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$a = $this->checkNumber($a);

		$this->checkAddress($a);

		$this->stack->push($this->pc + 2);
		$this->pc = $a;
	}

	private function ret(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - ret\n";
		}

		try {
			$address = $this->stack->pop();
		} catch (\UnderflowException $e) {
			$this->halt();
			return;
		}

		$this->checkAddress($address);

		$this->pc = $address;
	}

	private function out(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - out\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);
		$a = $this->checkNumber($a);

		echo chr($a);

		$this->pc += 2;
	}

	private function in(): void
	{
		if ($this->debug) {
			echo "PC: $this->pc - in\n";
		}

		$address = $this->pc + 1;
		$a = $this->readFrom($address);

		if ($this->inputBuffer->isEmpty()) {
			$proceed = false;
			while (!$proceed) {
				$input = readline('(r for registers, b for bypass) >');
	
				if ($input === "r") {
					for ($i = 0; $i < 8; $i++) {
						echo "R$i = {$this->register[$i]}\n";
					}
				} else if (str_starts_with($input, "r=")) {
					$value = str_replace("r=", "", $input);
					$this->register[7] = intval($value);
				} else if ($input === "b") {
					// TODO: bypass
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
		if ($this->debug) {
			echo "PC: $this->pc - noop\n";
		}

		$this->pc++;
	}
}