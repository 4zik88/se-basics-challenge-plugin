<?php
/**
 * Seed the question bank with a starter set of Sales-Engineering-basics questions.
 * Run via: wp eval-file wp-content/plugins/naase-challenge/bin/seed-questions.php
 * No-op if the bank already has questions.
 *
 * @package NAASE_Challenge
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'NAASE_Questions' ) ) {
	WP_CLI::error( 'NAASE plugin not loaded.' );
	return;
}

if ( NAASE_Questions::count_active() > 0 ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( 'Question bank already populated — skipping seed.' );
	}
	return;
}

$questions = array(
	array(
		'question_text'  => 'What best describes the core function of a Sales Engineer (SE)?',
		'answer_a'       => 'Combining technical expertise with sales skills to help customers choose the right solution',
		'answer_b'       => 'Managing internal financial reporting and audit processes',
		'answer_c'       => 'Building new electronic hardware for factory automation',
		'answer_d'       => 'Leading customer onboarding after contracts are signed',
		'correct_answer' => 'A',
		'knowledge_area' => 'SE Fundamentals',
		'difficulty'     => 2,
		'public_note'    => 'This is the kind of question often asked in early SE interviews.',
		'internal_note'  => 'Anchor question — keep.',
	),
	array(
		'question_text'  => 'During discovery, what is the MOST important thing an SE should do?',
		'answer_a'       => 'Demo every product feature available',
		'answer_b'       => 'Ask questions to understand the customer’s real business problems',
		'answer_c'       => 'Quote the lowest possible price immediately',
		'answer_d'       => 'Send the full technical documentation',
		'correct_answer' => 'B',
		'knowledge_area' => 'Discovery',
		'difficulty'     => 2,
		'public_note'    => 'Discovery is about problems before products.',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'What is the primary goal of a great product demo?',
		'answer_a'       => 'Show as many features as possible',
		'answer_b'       => 'Impress with technical jargon',
		'answer_c'       => 'Tell a relevant story that maps to the customer’s needs',
		'answer_d'       => 'Finish as quickly as possible',
		'correct_answer' => 'C',
		'knowledge_area' => 'Demos',
		'difficulty'     => 3,
		'public_note'    => 'Demos should be tailored, not exhaustive.',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'A prospect raises a technical objection you can’t fully answer. What’s the best response?',
		'answer_a'       => 'Make up a plausible-sounding answer',
		'answer_b'       => 'Acknowledge it, commit to following up, and do so promptly',
		'answer_c'       => 'Ignore it and move on',
		'answer_d'       => 'Tell them it isn’t important',
		'correct_answer' => 'B',
		'knowledge_area' => 'Buyer Trust',
		'difficulty'     => 3,
		'public_note'    => 'Honesty builds trust faster than bluffing.',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'What does “technical win” typically mean in a sales cycle?',
		'answer_a'       => 'The contract has been signed',
		'answer_b'       => 'The customer’s technical stakeholders are convinced the solution works for them',
		'answer_c'       => 'The product passed internal QA',
		'answer_d'       => 'The price was approved by finance',
		'correct_answer' => 'B',
		'knowledge_area' => 'Sales Process',
		'difficulty'     => 4,
		'public_note'    => '',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'When communicating with non-technical buyers, an SE should primarily focus on:',
		'answer_a'       => 'Detailed system architecture diagrams',
		'answer_b'       => 'Business outcomes and value',
		'answer_c'       => 'Source code quality',
		'answer_d'       => 'Database schema design',
		'correct_answer' => 'B',
		'knowledge_area' => 'Technical Communication',
		'difficulty'     => 2,
		'public_note'    => 'Match the message to the audience.',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'What is a Proof of Concept (POC) primarily used for?',
		'answer_a'       => 'To validate the solution against the customer’s specific requirements',
		'answer_b'       => 'To replace the sales contract',
		'answer_c'       => 'To train the support team',
		'answer_d'       => 'To benchmark competitors’ marketing',
		'correct_answer' => 'A',
		'knowledge_area' => 'POC / Evaluation',
		'difficulty'     => 3,
		'public_note'    => '',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'Which metric best reflects the business impact of a solution for a customer?',
		'answer_a'       => 'Number of features shipped',
		'answer_b'       => 'Lines of configuration',
		'answer_c'       => 'Return on investment (ROI) or time saved',
		'answer_d'       => 'Number of demo slides',
		'correct_answer' => 'C',
		'knowledge_area' => 'Business Impact',
		'difficulty'     => 3,
		'public_note'    => '',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'What is the SE’s role in handling an RFP (Request for Proposal)?',
		'answer_a'       => 'Provide accurate technical responses and highlight differentiators',
		'answer_b'       => 'Set the legal terms',
		'answer_c'       => 'Approve the customer’s budget',
		'answer_d'       => 'Write the customer’s internal policy',
		'correct_answer' => 'A',
		'knowledge_area' => 'Sales Process',
		'difficulty'     => 4,
		'public_note'    => '',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'Why is qualification (e.g., MEDDIC/BANT) useful to an SE?',
		'answer_a'       => 'It guarantees a sale',
		'answer_b'       => 'It helps focus technical effort on deals likely to close',
		'answer_c'       => 'It replaces the need for demos',
		'answer_d'       => 'It sets the product roadmap',
		'correct_answer' => 'B',
		'knowledge_area' => 'Sales Process',
		'difficulty'     => 5,
		'public_note'    => 'Qualification protects your time.',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'A customer asks for a feature your product doesn’t have. Best approach?',
		'answer_a'       => 'Promise it will ship next week',
		'answer_b'       => 'Understand the underlying need and explore alternatives or roadmap',
		'answer_c'       => 'Tell them no and end the conversation',
		'answer_d'       => 'Ignore the request',
		'correct_answer' => 'B',
		'knowledge_area' => 'Discovery',
		'difficulty'     => 3,
		'public_note'    => '',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'What is the main benefit of collaborating closely with Account Executives (AEs)?',
		'answer_a'       => 'The SE can avoid talking to customers',
		'answer_b'       => 'Aligned technical and commercial strategy increases win rates',
		'answer_c'       => 'It removes the need for discovery',
		'answer_d'       => 'It lets the SE set pricing',
		'correct_answer' => 'B',
		'knowledge_area' => 'Teamwork',
		'difficulty'     => 2,
		'public_note'    => '',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'How should an SE handle a competitive deal where a rival is favored?',
		'answer_a'       => 'Disparage the competitor aggressively',
		'answer_b'       => 'Focus on the customer’s requirements and where your solution genuinely fits best',
		'answer_c'       => 'Lower the price below cost without approval',
		'answer_d'       => 'Withdraw immediately',
		'correct_answer' => 'B',
		'knowledge_area' => 'Buyer Trust',
		'difficulty'     => 5,
		'public_note'    => '',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'What makes a demo environment effective?',
		'answer_a'       => 'It is generic and never changes',
		'answer_b'       => 'It reflects realistic, customer-relevant scenarios and data',
		'answer_c'       => 'It contains only placeholder text',
		'answer_d'       => 'It is hidden from the customer',
		'correct_answer' => 'B',
		'knowledge_area' => 'Demos',
		'difficulty'     => 3,
		'public_note'    => '',
		'internal_note'  => '',
	),
	array(
		'question_text'  => 'After a technical win, what should the SE typically help ensure?',
		'answer_a'       => 'A smooth handoff to implementation/onboarding',
		'answer_b'       => 'That the customer never contacts support',
		'answer_c'       => 'That the deal is cancelled',
		'answer_d'       => 'That all documentation is deleted',
		'correct_answer' => 'A',
		'knowledge_area' => 'Post-Sale',
		'difficulty'     => 3,
		'public_note'    => '',
		'internal_note'  => '',
	),
);

$created = 0;
foreach ( $questions as $q ) {
	if ( NAASE_Questions::insert( NAASE_Questions::sanitize( $q ) ) ) {
		$created++;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::success( "Seeded {$created} questions." );
}
