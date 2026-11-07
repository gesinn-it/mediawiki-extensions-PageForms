<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\CreatePageJob;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\PageForms\CreatePageJob
 * @group Database
 */
class CreatePageJobTest extends MediaWikiIntegrationTestCase {

	public function testRunCreatesPageWithGivenTextAndSummary(): void {
		$title = Title::makeTitle( NS_MAIN, 'PFTestCreatePageJobNewPage' );
		$user = $this->getTestSysop()->getUser();

		$job = new CreatePageJob( $title, [
			'page_text' => 'Content from the job',
			'edit_summary' => 'Created via CreatePageJob',
			'user_id' => $user->getId(),
		] );

		$this->assertTrue( $job->run() );

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->assertTrue( $page->exists() );
		$this->assertSame(
			'Content from the job',
			$page->getContent()->serialize()
		);
		$revision = $page->getRevisionRecord();
		$this->assertSame( 'Created via CreatePageJob', $revision->getComment()->text );
	}

	public function testRunWithoutEditSummaryUsesEmptySummary(): void {
		$title = Title::makeTitle( NS_MAIN, 'PFTestCreatePageJobNoSummary' );
		$user = $this->getTestSysop()->getUser();

		$job = new CreatePageJob( $title, [
			'page_text' => 'Some content',
			'user_id' => $user->getId(),
		] );

		$this->assertTrue( $job->run() );

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$revision = $page->getRevisionRecord();
		$this->assertSame( '', $revision->getComment()->text );
	}

	public function testCreateOrModifyPageAttachesBotFlagForBotUser(): void {
		$title = Title::makeTitle( NS_MAIN, 'PFTestCreatePageJobBotEdit' );
		$user = $this->getTestSysop()->getUser();
		$this->getServiceContainer()->getUserGroupManager()->addUserToGroup( $user, 'bot' );
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );

		CreatePageJob::createOrModifyPage( $wikiPage, 'Bot content', 'bot summary', $user );

		$revision = $wikiPage->getRevisionRecord();
		$this->assertTrue( $revision->getSlot( MediaWiki\Revision\SlotRecord::MAIN )->getContent()->equals(
			new WikitextContent( 'Bot content' )
		) );

		// The bot-flagged edit must be marked as a bot edit on the recent changes entry.
		$rc = RecentChange::newFromConds( [ 'rc_this_oldid' => $revision->getId() ] );
		$this->assertNotNull( $rc, 'A recent-changes entry must exist for the bot edit' );
		$this->assertSame( '1', (string)$rc->getAttribute( 'rc_bot' ) );
	}
}
