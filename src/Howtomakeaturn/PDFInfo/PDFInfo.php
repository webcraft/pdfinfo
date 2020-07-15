<?php
namespace Howtomakeaturn\PDFInfo;

/*
* Inspired by http://stackoverflow.com/questions/14644353/get-the-number-of-pages-in-a-pdf-document/14644354
* @author howtomakeaturn
*/

use Symfony\Component\Process\Process;

class PDFInfo
{
    protected $file;
    protected $page;
    protected $maxPage;
    public $output;

    public $title;
    public $author;
    public $creator;
    public $producer;
    public $creationDate;
    public $modDate;
    public $tagged;
    public $form;
    public $pages;
    public $encrypted;
    public $pageSize;
    public $pageDimensions;
    public $pageRot;
    public $fileSize;
    public $optimized;
    public $PDFVersion;

    public static $bin;

    public function __construct($file, $page = null, $maxPage = null)
    {
        $this->file = $file;
        $this->page = $page;
        $this->maxPage = $maxPage;

        if ($this->maxPage < $this->page) {
            $this->maxPage = $this->page;
        }

        $this->loadOutput();

        $this->parseOutput();
    }

    public function getBinary()
    {
        if (empty(static::$bin)) {
            static::$bin = trim(trim(getenv('PDFINFO_BIN'), '\\/" \'')) ?: 'pdfinfo';
        }

        return static::$bin;
    }

    private function loadOutput()
    {
        $arguments = [$this->getBinary(), $this->file];
        if ($this->page) {
            $arguments[] = '-f';
            $arguments[] = $this->page;

            if ($this->maxPage) {
                $arguments[] = '-l';
                $arguments[] = $this->maxPage;
            } else {
                $arguments[] = '-l';
                $arguments[] = $this->page;
            }
        }

        // Parse entire output
        // Surround with double quotes if file name has spaces
        $process = new Process($arguments);
        $process->run();
        $output = $process->getOutput();
        $returnVar = $process->getExitCode();

        if ( $returnVar === 1 ){
            throw new Exceptions\OpenPDFException();
        } else if ( $returnVar === 2 ){
            throw new Exceptions\OpenOutputException();
        } else if ( $returnVar === 3 ){
            throw new Exceptions\PDFPermissionException();
        } else if ( $returnVar === 99 ){
            throw new Exceptions\OtherException();
        } else if ( $returnVar === 127 ){
            throw new Exceptions\CommandNotFoundException();
        }

        $this->output = $output;
    }

    private function parseOutput()
    {
        $this->title = $this->parse('Title');
        $this->author = $this->parse('Author');
        $this->creator = $this->parse('Creator');
        $this->producer = $this->parse('Producer');
        $this->creationDate = $this->parse('CreationDate');
        $this->modDate = $this->parse('ModDate');
        $this->tagged = $this->parse('Tagged');
        $this->form = $this->parse('Form');
        $this->pages = $this->parse('Pages');
        $this->encrypted = $this->parse('Encrypted');
        $this->fileSize = $this->parse('File size');
        $this->optimized = $this->parse('Optimized');
        $this->PDFVersion = $this->parse('PDF version');

        // Page specific properties
        if ($this->page && $this->maxPage) {
            $maxPage = $this->maxPage <= $this->pages ? $this->maxPage : $this->pages;
            for ($i = $this->page; $i <= $maxPage; $i++) {
                $pageSize = $this->parse('Page ' . $i . ' size');
                $pageRot = $this->parse('Page ' . $i . ' rot');
                $this->pageDimensions[$i] = [
                    'size' => $pageSize,
                    'rot' => $pageRot
                ];
            }

            $this->pageSize = $this->pageDimensions[$this->page]['size'];
            $this->pageRot = $this->pageDimensions[$this->page]['rot'];
        } else {
            if ($this->page) {
                $this->pageSize = $this->parse('Page ' . $this->page . ' size');
                $this->pageRot = $this->parse('Page ' . $this->page . ' rot');

            } else {
                $this->pageSize = $this->parse('Page size');
                $this->pageRot = $this->parse('Page rot');
            }

            $this->pageDimensions[$this->page] = [
                'size' => $this->pageSize,
                'rot' => $this->pageRot
            ];
        }
    }

    private function parse($attribute)
    {
        // Iterate through lines
        $result = null;
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $this->output) as $line) {
            // Clean multiple spaces in the key
            // It happens when we use pdfinfo with a specific page
            $cleanedOp = preg_replace('!\s+!', ' ', $line);
            // Extract the number
            if(preg_match("/" . $attribute . ":\s*(.+)/i", $cleanedOp, $matches) === 1) {
                $result = $matches[1];
                break;
            }
        }

        return $result;
    }

}
