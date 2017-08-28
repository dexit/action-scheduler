<?php

/**
 * Abstract class with common Queue Cleaner functionality.
 */
abstract class ActionScheduler_Abstract_QueueRunner {

	/** @var ActionScheduler_QueueCleaner */
	protected $cleaner;

	/** @var ActionScheduler_FatalErrorMonitor */
	protected $monitor;

	/** @var ActionScheduler_Store */
	protected $store;

	/**
	 * ActionScheduler_Abstract_QueueRunner constructor.
	 *
	 * @param ActionScheduler_Store             $store
	 * @param ActionScheduler_FatalErrorMonitor $monitor
	 * @param ActionScheduler_QueueCleaner      $cleaner
	 */
	public function __construct( ActionScheduler_Store $store, ActionScheduler_FatalErrorMonitor $monitor, ActionScheduler_QueueCleaner $cleaner ) {
		$this->store   = $store;
		$this->monitor = $monitor;
		$this->cleaner = $cleaner;
	}

	/**
	 * Process an individual action.
	 *
	 * @param int $action_id The action ID to process.
	 */
	public function process_action( $action_id ) {
		$action = $this->store->fetch_action( $action_id );
		try {
			do_action( 'action_scheduler_before_execute', $action_id );
			$this->store->log_execution( $action_id );
			$action->execute();
			do_action( 'action_scheduler_after_execute', $action_id );
			$this->store->mark_complete( $action_id );
		} catch ( Exception $e ) {
			$this->store->mark_failure( $action_id );
			do_action( 'action_scheduler_failed_execution', $action_id, $e );
		}
		$this->schedule_next_instance( $action );
	}

	/**
	 * Schedule the next instance of the action if necessary.
	 *
	 * @param ActionScheduler_Action $action
	 */
	protected function schedule_next_instance( ActionScheduler_Action $action ) {
		$schedule = $action->get_schedule();
		$next     = $schedule->next( as_get_datetime_object() );

		if ( ! is_null( $next ) && $schedule->is_recurring() ) {
			$this->store->save_action( $action, $next );
		}
	}

	/**
	 * Run the queue cleaner.
	 *
	 * @author Jeremy Pry
	 */
	protected function run_cleanup() {
		$cleaner = new ActionScheduler_QueueCleaner( $this->store );
		$cleaner->clean();
	}

	/**
	 * Process actions in the queue.
	 *
	 * @author Jeremy Pry
	 * @return int The number of actions processed.
	 */
	abstract public function run();
}
