<?php
/**
 * J2T RewardsPoint2
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@j2t-design.com so we can send you a copy immediately.
 *
 * @category   Magento extension
 * @package    RewardsPoint2
 * @copyright  Copyright (c) 2009 J2T DESIGN. (http://www.j2t-design.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class J2t_Rewardpoints_Block_Adminhtml_Widget_Grid extends Mage_Adminhtml_Block_Widget_Grid{
    
    public function getPdfFile(){
        $this->_isExport = true;
        $this->_prepareGrid();
        $this->getCollection()->getSelect()->limit();
        $this->getCollection()->setPageSize(0);
        $this->getCollection()->load();
        $this->_afterLoadCollection();
        
        
        $module_path = Mage::getModuleDir('', 'J2t_Rewardpoints').DS.'lib'.DS.'frameworkPdf'.DS;
        require_once($module_path.'Pdf.php');
        
        $pdf = new Framework_Pdf();
        $pdf->setPaperSize(2);
        //$pdf->setHeaderImage(getcwd() . '/' . $this->imageDir() . '/logo-main.png');

        $table = $pdf->addTable(array('align'=>'justify', 'no_wrap' => True));
        $table->addSpacerRow();
        $table->addRow(array('underline' => True))
                ->addCol("Some Header", array('bold' => true, 'colspan' => 3, 'align' => 'center'));
        $table->addRow()
                ->addCol("Column Text 1", array('bold' => true, 'align' => 'center', 'colspan'=>3))
                ->addCol(" ")
                ->addCol("Column Text 2", array('bold' => true, 'align' => 'center', 'colspan'=>3));

        $pdf->addPage();
        $table = $pdf->addTable(array('align'=>'justify', 'no_wrap' => True));
        // Per popular request, an example of adding columns using a loop
        $row = $table->addRow();

        for($i = 0; $i < $someConstraint; $i++) {
                $row->addCol("Some Info");
        }

        return $pdf->build();
        
        

        $pdf = new Zend_Pdf();
        $page = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_A4);
        $font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES);
        $page->setFont($font, 12);
        $width = $page->getWidth();
        
        //Draw table header row’s
        $y = $page->getHeight();
        $page->drawRectangle(30, $y - 38, $page->getWidth()-60, $y + 12, Zend_Pdf_Page::SHAPE_DRAW_STROKE);
        
        $i=0;
        foreach ($this->_columns as $column) {
            
            if (!$column->getIsSystem()) {
                $i+=10;
                $header = $column->getExportHeader();  
                $page->drawText($header, $i, $page->getHeight()-20);                
                $width = $font->widthForGlyph($font->glyphNumberForCharacter($header));
                $i+=($width/$font->getUnitsPerEm()*12)*strlen($header)+10;
            }
        }
        
        //$this->_exportIterateCollection('_exportPdfItem', array($page));
        
        $pdf->pages[] = $page;
        return $pdf->render();
    }
    
    protected function _exportPdfItem(Varien_Object $item, Zend_Pdf_Page $page)
    {
        $row = array();
        $i=0;
        $font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES);
        foreach ($this->_columns as $column) {
            if (!$column->getIsSystem()) {
                //$row[] = $column->getRowFieldExport($item);
                $i+=10;
                $header = $column->getRowFieldExport($item);  
                $page->drawText($header, $i, $page->getHeight()-20);                
                $width = $font->widthForGlyph($font->glyphNumberForCharacter($header));
                $i+=($width/$font->getUnitsPerEm()*12)*strlen($header)+10;
            }
        }
        //$page->streamWriteCsv($row);
    }
}
