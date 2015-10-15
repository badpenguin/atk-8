<?php namespace Sintattica\Atk\Utils;
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage utils
 *
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 6302 $
 * $Id$
 */

/**
 * File download exporter.
 *
 * Can write any string to a file and send it as download to the browser.
 *
 * @author Sandy Pleyte <sandy@achievo.org>
 * @package atk
 * @subpackage handlers
 *
 */
class FileExport
{

    /**
     * Export data to a download file.
     *
     * BROWSER BUG:
     * IE has problems with the use of attachment; needs atachment (someone at MS can't spell) or none.
     * however ns under version 6 accepts this also.
     * NS 6+ has problems with the absense of attachment; and the misspelling of attachment;
     * at present ie 5 on mac gives wrong filename and NS 6+ gives wrong filename.
     *
     * @todo Currently supports only csv/excel mimetypes.
     * @param String $data The content
     * @param String $fileName Filename for the download
     * @param String $type The type (csv / excel / xml)
     * @param String $ext Extension of the file
     * @param String $compression Compression method (bzip / gzip)
     */
    function export($data, $fileName, $type, $ext = "", $compression = "")
    {
        ob_end_clean();
        if ($compression == "bzip") {
            $mime_type = 'application/x-bzip';
            $ext = "bz2";
        } elseif ($compression == "gzip") {
            $mime_type = 'application/x-gzip';
            $ext = "gz";
        } elseif ($type == "csv") {
            $mime_type = 'text/x-csv';
            $ext = "csv";
        } elseif ($type == "excel") {
            $mime_type = 'application/octet-stream';
            $ext = "xls";
        } elseif ($type == "xml") {
            $mime_type = 'text/xml';
            $ext = "xml";
        } else {
            $mime_type = 'application/octet-stream';
        }

        header('Content-Type: ' . $mime_type);
        header('Content-Disposition:  filename="' . $fileName . '.' . $ext . '"');

        // Fix for downloading (Office) documents using an SSL connection in
        // combination with MSIE.
        if (($_SERVER["SERVER_PORT"] == "443" || Tools::atkArrayNvl($_SERVER,
                    'HTTP_X_FORWARDED_PROTO') == "https") && eregi("msie", $_SERVER["HTTP_USER_AGENT"])
        ) {
            header('Pragma: public');
        } else {
            header('Pragma: no-cache');
        }

        header('Expires: 0');

        // 1. as a bzipped file
        if ($compression == "bzip") {
            if (@function_exists('bzcompress')) {
                echo bzcompress($data);
            }
        } // 2. as a gzipped file
        else {
            if ($compression == 'gzip') {
                if (@function_exists('gzencode')) {
                    // without the optional parameter level because it bug
                    echo gzencode($data);
                }
            } // 3. on screen
            else {
                if ($type == "csv" || $type == "excel") {
                    // in order to output UTF-8 content that Excel both on Windows and OS X will be able to successfully read
                    echo mb_convert_encoding($data, 'Windows-1252', 'UTF-8');
                } else {
                    echo $data;
                }
            }
        }

        flush();

        exit;
    }

}

