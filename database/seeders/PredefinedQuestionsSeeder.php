<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PredefinedQuestion;

class PredefinedQuestionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Global predefined questions (not tied to specific PDFs)
        $globalQuestions = [
            [
                'title' => 'Summarize Document',
                'question' => 'Please provide a comprehensive summary of this document in 3-5 key points.',
            ],
            [
                'title' => 'Main Topic',
                'question' => 'What is the main topic or subject of this document?',
            ],
            [
                'title' => 'Key Findings',
                'question' => 'What are the key findings, conclusions, or results presented in this document?',
            ],
            [
                'title' => 'Extract Tables',
                'question' => 'Extract any tables, data, or structured information from this document and present it in a readable format.',
            ],
            [
                'title' => 'List Methods',
                'question' => 'What methods, approaches, or methodologies are discussed in this document?',
            ],
            [
                'title' => 'Identify Datasets',
                'question' => 'List any datasets, data sources, or references mentioned in this document.',
            ],
            [
                'title' => 'Research Questions',
                'question' => 'What are the main research questions or objectives outlined in this document?',
            ],
            [
                'title' => 'Future Work',
                'question' => 'What future work, recommendations, or next steps are suggested in this document?',
            ],
            [
                'title' => 'Technical Details',
                'question' => 'What are the key technical details, specifications, or implementation aspects mentioned?',
            ],
            [
                'title' => 'Extract Quotes',
                'question' => 'Find and extract the most important quotes or statements from this document.',
            ],
        ];

        foreach ($globalQuestions as $question) {
            PredefinedQuestion::create([
                'pdf_id' => null, // Global question
                'title' => $question['title'],
                'question' => $question['question'],
            ]);
        }

        // Research paper specific questions
        $researchQuestions = [
            [
                'title' => 'Abstract Summary',
                'question' => 'Summarize the abstract of this research paper.',
            ],
            [
                'title' => 'Literature Review',
                'question' => 'What related work or literature is reviewed in this paper?',
            ],
            [
                'title' => 'Experimental Setup',
                'question' => 'Describe the experimental setup or methodology used in this research.',
            ],
            [
                'title' => 'Results Analysis',
                'question' => 'What are the main results and how are they analyzed?',
            ],
            [
                'title' => 'Limitations',
                'question' => 'What limitations or constraints are acknowledged in this research?',
            ],
            [
                'title' => 'Contributions',
                'question' => 'What are the main contributions or novelties of this research?',
            ],
        ];

        foreach ($researchQuestions as $question) {
            PredefinedQuestion::create([
                'pdf_id' => null, // Global question
                'title' => $question['title'] . ' (Research)',
                'question' => $question['question'],
            ]);
        }

        // Business document specific questions
        $businessQuestions = [
            [
                'title' => 'Executive Summary',
                'question' => 'Provide an executive summary of this business document.',
            ],
            [
                'title' => 'Financial Information',
                'question' => 'Extract any financial data, numbers, or budget information mentioned.',
            ],
            [
                'title' => 'Action Items',
                'question' => 'List all action items, tasks, or recommendations from this document.',
            ],
            [
                'title' => 'Stakeholders',
                'question' => 'Who are the key stakeholders or parties mentioned in this document?',
            ],
            [
                'title' => 'Timeline',
                'question' => 'What timelines, deadlines, or schedules are mentioned in this document?',
            ],
            [
                'title' => 'Risks & Challenges',
                'question' => 'What risks, challenges, or concerns are identified in this document?',
            ],
        ];

        foreach ($businessQuestions as $question) {
            PredefinedQuestion::create([
                'pdf_id' => null, // Global question
                'title' => $question['title'] . ' (Business)',
                'question' => $question['question'],
            ]);
        }

        // Legal document specific questions
        $legalQuestions = [
            [
                'title' => 'Key Terms',
                'question' => 'What are the key terms and definitions in this legal document?',
            ],
            [
                'title' => 'Parties Involved',
                'question' => 'Who are the parties involved in this legal document?',
            ],
            [
                'title' => 'Obligations',
                'question' => 'What are the main obligations or responsibilities outlined?',
            ],
            [
                'title' => 'Important Dates',
                'question' => 'What important dates, deadlines, or time periods are specified?',
            ],
            [
                'title' => 'Terms & Conditions',
                'question' => 'Summarize the main terms and conditions.',
            ],
        ];

        foreach ($legalQuestions as $question) {
            PredefinedQuestion::create([
                'pdf_id' => null, // Global question
                'title' => $question['title'] . ' (Legal)',
                'question' => $question['question'],
            ]);
        }

        $this->command->info('Created ' . count($globalQuestions + $researchQuestions + $businessQuestions + $legalQuestions) . ' predefined questions.');
    }
}