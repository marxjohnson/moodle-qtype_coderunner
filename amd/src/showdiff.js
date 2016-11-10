// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This AMD module provides the functionality for the "Show differences"
 * button that is shown in the student's result page if their answer
 * isn't right and an "exact-match" (or near equivalent) grader is being used.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2016, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*********************************************************************
 *
 * coderunner_diff is a module providing a basic diff algorithm plus
 * functions to add/remove del tags to html elements to display their
 * differences.
 *
 * @module    qtype_coderunner/showdiff
 * @class     showdiff
 * @package   qtype_coderunner
 * @copyright 2016 Richard Lobb, University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 *
 **********************************************************************/

define(['jquery'], function($) {

    var NLCHAR = '\u21A9';  // Unicode "leftwards arrow with hook" to show newlines.

    function lcsLengths(items1, items2) {
        /* Given two lists of items, items1 and item2, return the length matrix
           M defined as M[i][j] = max subsequence length of the two item lists
           items1[0:i], items2[0:j]
         */
        var n1 = items1.length,
            n2 = items2.length,
            lengths, i, j;

        lengths = [];
        for (i = 0; i <= n1; i += 1) {
            lengths[i] = new Array(n2 + 1).fill(0);
        }
        for (i = 0; i < n1; i += 1) {
            for (j = 0; j < n2; j += 1) {
                if (items1[i] == items2[j]) {
                    lengths[i + 1][j + 1] = 1 + lengths[i][j];
                } else {
                    lengths[i + 1][j + 1] = Math.max(lengths[i][j + 1], lengths[i + 1][j]);
                }
            }
        }
        return lengths;
    }

    function lcss(items1, items2) {
        /* Return the longest common subsequence of the two item lists */
        var M, i, j, n, result, length;
        M = lcsLengths(items1, items2);
        length = M[items1.length][items2.length];
        result = [];
        i = items1.length;
        j = items2.length;
        n = length - 1;
        while (n >= 0) {
            if (items1[i - 1] == items2[j - 1]) {
                result[n] = items1[i - 1];
                n -= 1;
                i -= 1;
                j -= 1;
            } else if (M[i - 1][j] == M[i][j]) {
                i -= 1;
            } else {
                j -= 1;
            }
        }
        return result;
    }

    /* Process the given token list and a subsequence of it, joining tokens
     * with 'joiner' and wrapping all items not in the subsequence with
     * del tags (or whatever strings are specified by startDel, endDel).
     * Return (what is assumed to be) the html of all the joined tokens.
     */
    function insertDels(tokens, subSeq, joiner, startDel, endDel) {
        var html = "",
            deleting = false,
            i,
            ssi = 0;
        if (startDel === undefined) {
            startDel = '<del>';
        }
        if (endDel === undefined) {
            endDel = '</del>';
        }
        for (i = 0; i < tokens.length; i += 1) {
            if (ssi < subSeq.length && tokens[i] == subSeq[ssi]) {
                if (deleting) {
                    html += endDel;
                    deleting = false;
                }
                ssi += 1;
            } else {
                if (!deleting) {
                    html += startDel;
                    deleting = true;
                }
            }
            if (i !== 0) {
                html += joiner;
            }
            html += tokens[i];
        }
        if (deleting) {
            html += endDel;
        }
        return html;
    }

    /* Return the HTML element type (i.e. its tag name) in lower case */
    function elType(elem) {
        return elem.tagName.toLowerCase();
    }

    /* Return a sequence of textual units of the type
     * dictated by the given splitter. Extra 'leftward-arrow-with-hook'
     * characters (\u21A9) are added at the ends of lines if showNls is true
     */
    function getSequence(element, splitter, showNls) {
        var isPre = elType(element) === 'pre',
            text = element.innerHTML,
            seq;

        if (showNls) {
            if (isPre) {
                text = text.replace(/\n/g, NLCHAR + '\n');
            }
            text = text.replace(/(<br ?.*?>)/g, NLCHAR + '$1');
        }

        seq = text.split(splitter);
        return seq;
    }

    /* Given (references to) two HTML elements, extract the innerHTML
     * of both, find the longest common subsequence of chars and wrap text not
     * in that subsequence in del elements.
     * <br> elements within the innerHTML are preceded by a
     * Unicode "leftwards arrow with hook" ('\u21A9') so that line break changes
     * can be highlighted.
     */
    function showDifferences(firstEl, secondEl) {
        var splitter = '',
            showNls = true,
            openDelTag = '<del>',
            closeDelTag = '</del>',
            seq1,
            seq2,
            css;

        seq1 = getSequence(firstEl, splitter, showNls);
        seq2 = getSequence(secondEl, splitter, showNls);
        css = lcss(seq1, seq2);
        firstEl.innerHTML = insertDels(seq1, css, splitter, openDelTag, closeDelTag);
        secondEl.innerHTML = insertDels(seq2, css, splitter, openDelTag, closeDelTag);
    }

    /* Given (references to) two DOM elements, delete all <del ...> and </del>
     * tags from the innerHTML of both. Also remove the "leftwards arrows with
     * hooks".
     */
    function hideDifferences(firstEl, secondEl) {
        var replPat = new RegExp('(</?del[^>]*>)|(' + NLCHAR + ')', 'g');
        firstEl.innerHTML = firstEl.innerHTML.replace(replPat, '');
        secondEl.innerHTML = secondEl.innerHTML.replace(replPat, '');
    }

    /************************************************************************
     *
     * Now the API for applying diffs to rows in a CodeRunner
     * results table. Defines a class with methods initDiffButton and
     * processAllRows.
     *
     *************************************************************************/

    function processAllRows(tableRows, gotCol, expectedCol, f) {
        var row,
            cells,
            expected,
            got;

        for (var i = 0; i < tableRows.length; i++) {
            row = tableRows[i];
            cells = row.getElementsByTagName('td');
            expected = cells[expectedCol];
            got = cells[gotCol];
            f(expected, got);
        }
    }

    /**
     * Initialise the Show Differences button.
     * @param string buttonId The ID of the Show Differences button
     * @param string showValue the text in the button initially
     * @param string hideValue the text in the button when differences are showing
     * @returns undefined
     */
    function initDiffButton(buttonId, showValue, hideValue) {
        var diffButton = $('[id="' + buttonId + '"]'),
            tableRows = $('table.coderunner-test-results tbody tr'),
            thEls = $('table.coderunner-test-results th'),
            columnCount = 0,
            gotCol,
            expectedCol;

        thEls.each(function() {
            if ($(this).html() === 'Got') {
                gotCol = columnCount;
            } else if ($(this).html() === 'Expected') {
                expectedCol = columnCount;
            }
            columnCount += 1;
        });

        if (gotCol && expectedCol) {
            diffButton.on("click", function() {
                if (diffButton.prop('value') === showValue) {
                    processAllRows(tableRows.toArray(), gotCol, expectedCol, showDifferences);
                    diffButton.prop('value', hideValue);
                } else {
                    processAllRows(tableRows.toArray(), gotCol, expectedCol, hideDifferences);
                    diffButton.prop('value', showValue);
                }
            });
        } else {
            diffButton.enabled = false;
        }
    }

    return { "initDiffButton": initDiffButton };
});