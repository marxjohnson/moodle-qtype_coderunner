<?php
namespace RunguardSandbox;

/* ==============================================================
 *
 * This file contains all the RunguardSandbox Language definitions.
 *
 * ==============================================================
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class Matlab_Task extends LanguageTask {
    public function __construct($sandbox, $source) {
        LanguageTask::__construct($sandbox, $source);
    }

    public function getVersion() {
        return 'Matlab R2012';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName . '.m';
        if (!copy($this->sourceFileName, $this->executableFileName)) {
            throw new coding_exception("Matlab_Task: couldn't copy source file");
        }
    }

    public function getRunCommand() {
         $filesize = 1000000 * $this->sandbox->getParam('disklimit');
         $memsize = 1000 * $this->sandbox->getParam('memorylimit');
         $cputime = $this->sandbox->getParam('cputime');
         $numProcs = $this->sandbox->getParam('numprocs');
         return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=$cputime",       // Seconds of execution time allowed
             "--memsize=0",           // TODO: why won't MATLAB run with a memsize set?!!
             "--filesize=$filesize",  // Max file sizes
             "--nproc=200",            // At most 20 processes/threads (for this *user*)
             // Number increased to 200 to allow multiple web threads to be
             // running Matlab at once. This is ugly.
             // TODO:  is there a better way to prevent fork bombs? cgroups?
             "--no-core",
             "--streamsize=$filesize",   // Max stdout/stderr sizes
             '/usr/local/bin/matlab_exec_cli',
             '-nojvm',
             '-r',
             basename($this->sourceFileName)
         );
     }


     public function filterOutput($out) {
         $lines = explode("\n", $out);
         $outlines = array();
         $headerEnded = FALSE;

         foreach ($lines as $line) {
             $line = rtrim($line);
             if ($headerEnded) {
                 $outlines[] = $line;
             }
             if (strpos($line, 'For product information, visit www.mathworks.com.') !== FALSE) {
                 $headerEnded = TRUE;
             }
         }

         // Remove blank lines at the start and end
         while (count($outlines) > 0 && strlen($outlines[0]) == 0) {
             array_shift($outlines);
         }
         while(count($outlines) > 0 && strlen(end($outlines)) == 0) {
             array_pop($outlines);
         }

         return implode("\n", $outlines) . "\n";
     }
};

class Python2_Task extends LanguageTask {
    public function __construct($sandbox, $source) {
        LanguageTask::__construct($sandbox, $source);
    }

    public function getVersion() {
        return 'Python 2.7';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName;
    }


    // Return the command to pass to localrunner as a list of arguments,
    // starting with the program to run followed by a list of its arguments.
    public function getRunCommand() {
        $filesize = 1000000 * $this->sandbox->getParam('disklimit');
        $memsize = 1000 * $this->sandbox->getParam('memorylimit');
        $cputime = $this->sandbox->getParam('cputime');
        $numProcs = $this->sandbox->getParam('numprocs');
        return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=$cputime",         // Seconds of execution time allowed
             "--memsize=$memsize",      // Max kb mem allowed (100MB)
             "--filesize=$filesize",    // Max file sizes (10MB)
             "--nproc=$numProcs",       // Max num processes/threads for this *user*
             "--no-core",
             "--streamsize=$filesize",  // Max stdout/stderr sizes
             '/usr/bin/python2',
             '-BESs',
             $this->sourceFileName
         );
     }
};

class Python3_Task extends LanguageTask {
    public function __construct($sandbox, $source) {
        LanguageTask::__construct($sandbox, $source);
    }

    public function getVersion() {
        return 'Python 3.2';
    }

    public function compile() {
        exec("python3 -m py_compile {$this->sourceFileName} 2>compile.out", $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $this->sourceFileName;
        }
        else {
            $this->cmpinfo = file_get_contents('compile.out');
        }
    }


    // Return the command to pass to localrunner as a list of arguments,
    // starting with the program to run followed by a list of its arguments.
    public function getRunCommand() {
        $filesize = 1000000 * $this->sandbox->getParam('disklimit');
        $memsize = 1000 * $this->sandbox->getParam('memorylimit');
        $cputime = $this->sandbox->getParam('cputime');
        $numProcs = $this->sandbox->getParam('numprocs');
        return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=$cputime",         // Seconds of execution time allowed
             "--memsize=$memsize",      // Max kb mem allowed (100MB)
             "--filesize=$filesize",    // Max file sizes (10MB)
             "--nproc=$numProcs",       // Max num processes/threads for this *user*
             "--no-core",
             "--streamsize=$filesize",  // Max stdout/stderr sizes
             '/usr/bin/python3',
             '-BE',
             $this->sourceFileName
         );
     }
};

class Java_Task extends LanguageTask {
    public function __construct($sandbox, $source) {
        LanguageTask::__construct($sandbox, $source);
    }

    public function getVersion() {
        return 'Java 1.6';
    }

    public function compile() {
        $prog = file_get_contents($this->sourceFileName);
        if (($this->mainClassName = $this->getMainClass($prog)) === FALSE) {
            $this->cmpinfo = "Error: no main class found, or multiple main classes. [Did you write a public class when asked for a non-public one?]";
        }
        else {
            exec("mv {$this->sourceFileName} {$this->mainClassName}.java", $output, $returnVar);
            if ($returnVar !== 0) {
                throw new coding_exception("Java compile: couldn't rename source file");
            }
            $this->sourceFileName = "{$this->mainClassName}.java";
            exec("/usr/bin/javac {$this->sourceFileName} 2>compile.out", $output, $returnVar);
            if ($returnVar == 0) {
                $this->cmpinfo = '';
                $this->executableFileName = $this->sourceFileName;
            }
            else {
                $this->cmpinfo = file_get_contents('compile.out');
            }
        }
    }


    public function getRunCommand() {
        $filesize = 1000000 * $this->sandbox->getParam('disklimit');
        $memsize = 1000 * $this->sandbox->getParam('memorylimit');
        $cputime = $this->sandbox->getParam('cputime');
        $numProcs = $this->sandbox->getParam('numprocs');
        return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=$cputime",         // Seconds of execution time allowed
             "--memsize=$memsize",      // Max kb mem allowed (100MB)
             "--filesize=$filesize",    // Max file sizes (10MB)
             "--nproc=$numProcs",       // Max num processes/threads for this *user*
             "--no-core",
             "--streamsize=$filesize",  // Max stdout/stderr sizes
             '/usr/bin/java',
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m",
             $this->mainClassName
         );
    }


     // Return the name of the main class in the given prog, or FALSE if no
     // such class found. Uses a regular expression to find a public class with
     // a public static void main method.
     // Not totally safe as it doesn't parse the file, e.g. would be fooled
     // by a commented-out main class with a different name.
     private function getMainClass($prog) {
         $pattern = '/(^|\W)public\s+class\s+(\w+)\s*\{.*?public\s+static\s+void\s+main\s*\(\s*String/ms';
         if (preg_match_all($pattern, $prog, $matches) !== 1) {
             return FALSE;
         }
         else {
             return $matches[2][0];
         }
     }
};


class C_Task extends LanguageTask {

    public function __construct($sandbox, $source) {
        LanguageTask::__construct($sandbox, $source);
    }

    public function getVersion() {
        return 'gcc-4.6.3';
    }

    public function compile() {
        $src = basename($this->sourceFileName);
        $errorFileName = "$src.err";
        $execFileName = "$src.exe";
        $cmd = "gcc -Wall -Werror -std=c99 -x c -o $execFileName $src -lm 2>$errorFileName";
        // To support C++ instead use something like ...
        // $cmd = "g++ -Wall -Werror -x ++ -o $execFileName $src -lm 2>$errorFileName";
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $execFileName;
        }
        else {
            $this->cmpinfo = file_get_contents($errorFileName);
        }
    }


    public function getRunCommand() {
        $filesize = 1000000 * $this->sandbox->getParam('disklimit');
        $memsize = 1000 * $this->sandbox->getParam('memorylimit');
        $cputime = $this->sandbox->getParam('cputime');
        $numProcs = $this->sandbox->getParam('numprocs');
        return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=$cputime",         // Seconds of execution time allowed
             "--memsize=$memsize",      // Max kb mem allowed (100MB)
             "--filesize=$filesize",    // Max file sizes (10MB)
             "--nproc=$numProcs",       // Max num processes/threads for this *user*
             "--no-core",
             "--streamsize=$filesize",  // Max stdout/stderr sizes
             "./" . $this->executableFileName
         );
    }
};

?>
