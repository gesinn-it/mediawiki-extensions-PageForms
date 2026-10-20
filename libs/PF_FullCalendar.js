/**
 * Code to integrate the FullCalendar JavaScript library into Page Forms.
 *
 * @author Priyanshu Varshney
 */
/* global moment */
/* eslint-disable no-jquery/no-sizzle */
( function ( $, mw, pf ) {
	'use strict';

	$( '.pfFullCalendarJS' ).each( function() {

		$( '#fullCalendarLoading' ).css("display", "block");

		// This counter is used to assign unique ids to the calendar events.
		// If the event is deleted, we lose that unique id and can't be used gain.
		let counter = 0;
		const monthNames = mw.config.get('monthMessages');
		// Stuff from PF_FormPrinter.php
		const calendarParams = mw.config.get( 'wgPageFormsCalendarParams' );
		const calendarGridValues = mw.config.get( 'wgPageFormsCalendarValues' );
		const calendarHTML = mw.config.get('wgPageFormsCalendarHTML');

		const $fcDiv = $( this );
		const calendarId = $fcDiv.attr( 'id' );
		const templateName = $fcDiv.attr( 'template-name' );
		const eventTitleField = $fcDiv.attr( 'title-field' );
		const eventDateField = $fcDiv.attr( 'event-date-field' );
		const eventStartDateField = $fcDiv.attr( 'event-start-date-field' );
		const eventEndDateField = $fcDiv.attr( 'event-end-date-field' );
		let flagOneDayEvent = true;
		let pageLoaded = false;
		let isEventEndDateTime = false;
		let isEventStartTime = false;

		if( eventDateField === undefined ) {

			flagOneDayEvent = false;
		}

		const fieldType=[];
		// From here the game begins - getting the form HTML to be used as the popup form -
		// for the calendar interface
		const formHtml = calendarHTML[templateName];
		let popup = '<form id="popupForm">';
		const deleteButton = '<button id="event_delete" class = "delete-event-button" name="data" type="button" >' + mw.msg('pf-calendar-deleteevent') + '</button>';
		const createButton = '<button id="form_submit" class = "submit-event-button" name="data" type="button" >' + mw.msg('pf-calendar-createevent') + '</button>';
		const updateButton = '<button id="form_submit" class = "submit-event-button" name="data" type="button" >' + mw.msg('pf-calendar-updateevent') + '</button>';
		popup += formHtml;
		const createEventPopup = popup + createButton;
		const updateEventPopup = popup + updateButton;
		let suitableForCalendar = true;
		const calendarIdSelector = '#' + calendarId;
		const events = [], eventsNoDate = [];
		let data = [], dateFields = [], dateStartFields = [], dateEndFields = [], checkboxesNum = [];
		let segment, dateSegment, yearFC, monthFC, dateFC,
			timeSegment, hourFC, minuteFC, secondFC, ampm24h,
			dateEntry, monthEntry, yearEntry, hourEntry,
			minuteEntry, secondEntry, ampm24hEntry, regularEntry,
			eventData, preEventData, currParam, temp, titleIndex, monthIndex,
			eventDate, eventDateDay,eventDateYear, eventDateMonth, eventDateHour,
			eventStartDate, eventEndDate , eventDateMinute, eventDateSecond, eventDateAmPm24h, reserveDate, idForm,
			eventStartDateDay, eventStartDateYear, eventStartDateMonth, eventStartDateHour,
			eventStartDateMinute, eventStartDateSecond, eventStartDateAmPm24;
		let currentEndDateMoment;
		let checkboxesValues = [];
		let listboxValues = [];
		let tokensProto, comboboxProto, result, eventTemplateName, parameterName, eventContents, allEvents,
			dateElement, nextDate, formatted, i, j;

		const autoFillDay = templateName + '[cf][' + eventDateField + '][day]',
			autoFillMonth = templateName + '[cf][' + eventDateField + '][month]',
			autoFillYear = templateName + '[cf][' + eventDateField + '][year]',
			autoFillHour = templateName+'[cf]['+ eventDateField + '][hour]',
			autoFillMinute = templateName+'[cf]['+ eventDateField + '][minute]',
			autoFillSecond = templateName+'[cf]['+ eventDateField + '][second]',
			autoFillAmPm24h = templateName+'[cf]['+ eventDateField + '][ampm24h]',
			autoFillStartDay = templateName + '[cf][' + eventStartDateField + '][day]',
			autoFillStartMonth = templateName + '[cf][' + eventStartDateField + '][month]',
			autoFillStartYear = templateName + '[cf][' + eventStartDateField + '][year]',
			autoFillEndDay = templateName + '[cf][' + eventEndDateField + '][day]',
			autoFillEndMonth = templateName + '[cf][' + eventEndDateField + '][month]',
			autoFillEndYear = templateName + '[cf][' + eventEndDateField + '][year]',
			autoFillStartHour = templateName+'[cf]['+ eventStartDateField + '][hour]',
			autoFillStartMinute = templateName+'[cf]['+ eventStartDateField + '][minute]',
			autoFillStartSecond = templateName+'[cf]['+ eventStartDateField + '][second]',
			autoFillStartAmPm24h = templateName+'[cf]['+ eventStartDateField + '][ampm24h]',
			autoFillEndHour = templateName+'[cf]['+ eventEndDateField + '][hour]',
			autoFillEndMinute = templateName+'[cf]['+ eventEndDateField + '][minute]',
			autoFillEndSecond = templateName+'[cf]['+ eventEndDateField + '][second]',
			autoFillEndAmPm24h = templateName+'[cf]['+ eventEndDateField + '][ampm24h]';
		for( i = 0; i<calendarParams[templateName].length; i++ ) {
			fieldType[calendarParams[templateName][i].name]=calendarParams[templateName][i].type;
		}

		if( fieldType[eventStartDateField] === "datetime" || fieldType[eventDateField] === "datetime" ) {
			isEventStartTime = true;
		}

		if( fieldType[eventEndDateField] === "datetime") {
			isEventEndDateTime = true;
		}

		function saveData( flag ) {
			if(flag === 'single' ) {
				data = $('#popupForm').serializeArray();
				titleIndex = -1;
				dateFields = [];
				eventDate = '';
				for(j=0;j<data.length;j++){
					result = data[j].name.split("[");
					eventTemplateName = result[0];
					parameterName = result[2].split("]")[0];
					if( parameterName === eventTitleField ) {
						if( titleIndex === -1 ) {
							titleIndex = j;
						}
					} else if ( parameterName === eventDateField ) {
						dateFields.push({'name':data[j].name,'value':data[j].value});
						if( data[j].name.includes('second') ) {
							if( data[j].value === '' ) {
								data[j].value = '59';
							}
						} else if ( data[j].name.includes('hour') ) {
							if( data[j].value === '' ) {
								data[j].value = '23';
							}
						} else if ( data[j].name.includes('minute') ) {
							if( data[j].value === '' ) {
								data[j].value = '59';
							}
						}
					}
				}
			} else if ( flag === 'multiple' ) {
				data = $('#popupForm').serializeArray();
				titleIndex = -1;
				dateStartFields = [];
				dateEndFields = [];
				eventStartDate = '';
				eventEndDate = '';
				for(j=0;j<data.length;j++) {
					result = data[j].name.split("[");
					eventTemplateName = result[0];
					parameterName = result[2].split("]")[0];

					if( parameterName === eventTitleField ) {
						if( titleIndex === -1 ) {
							titleIndex = j;
						}
					} else if ( parameterName === eventStartDateField ) {
						dateStartFields.push({'name':data[j].name,'value':data[j].value});
						if( data[j].name.includes('second') ) {
							if( data[j].value === '' ) {
								data[j].value = '00';
							}
						} else if ( data[j].name.includes('hour') ) {
							if( data[j].value === '' ) {
								data[j].value = '00';
							}
						} else if ( data[j].name.includes('minute') ) {
							if( data[j].value === '' ) {
								data[j].value = '00';
							}
						}
					} else if ( parameterName === eventEndDateField ) {
						dateEndFields.push({'name':data[j].name,'value':data[j].value});
						if( data[j].name.includes('second') ) {
							if( data[j].value === '' ) {
								data[j].value = '59';
							}
						} else if ( data[j].name.includes('hour') ) {
							if( data[j].value === '' ) {
								data[j].value = '23';
							}
						} else if ( data[j].name.includes('minute') ) {
							if( data[j].value === '' ) {
								data[j].value = '59';
							}
						}
					}
				}
			}
		}
		// Checks if the date field parts are empty or not - if not,
		// don't allow the user to submit
		function checkAndSave( flag ) {
			if( flag === 'single' ) {
				$( ':input' ).on('keyup',() => {
					if(
						$( ':input[name="' + autoFillDay + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillYear + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillMonth + '"]' ).val() === ''
					) {
						$("#form_submit").attr("disabled", "disabled").css({'background-color':'#c8ccd1','color':'#fff'});
					} else {
						$("#form_submit").removeAttr("disabled").css({'background-color': '#3366CC','color': 'white'});
					}
				});
			} else if ( flag === 'multiple' ) {
				$( ':input' ).on('keyup',() => {
					if(
						$( ':input[name="' + autoFillStartDay + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillEndDay + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillStartYear + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillEndYear + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillStartMonth + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillEndMonth + '"]' ).val() === ''
					) {
						$("#form_submit").attr("disabled", "disabled").css({'background-color':'#c8ccd1','color':'#fff'});
					} else {
						let date1, date2;
						date1 = $( ':input[name="' + autoFillStartYear + '"]' ).val() + '-' +
						$( ':input[name="' + autoFillStartMonth + '"]' ).val() + '-' +
						padNumber($( ':input[name="' + autoFillStartDay + '"]' ).val());
						date2 = $( ':input[name="' + autoFillEndYear + '"]' ).val() + '-' +
						$( ':input[name="' + autoFillEndMonth + '"]' ).val() + '-' +
						padNumber($( ':input[name="' + autoFillEndDay + '"]' ).val());

						if( $( ':input[name="' + autoFillStartHour + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillStartMinute + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillStartSecond + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillEndHour + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillEndMinute + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillEndSecond + '"]' ).val() !== undefined) {
							date1 += ' ' + $( ':input[name="' + autoFillStartHour + '"]' ).val() + ':'
								+ $( ':input[name="' + autoFillStartMinute + '"]' ).val() + ':'
								+ $( ':input[name="' + autoFillStartSecond + '"]' ).val();
							date2 += ' ' + $( ':input[name="' + autoFillEndHour + '"]' ).val() + ':'
								+ $( ':input[name="' + autoFillEndMinute + '"]' ).val() + ':'
								+ $( ':input[name="' + autoFillEndSecond + '"]' ).val();
						}
						date1 = moment(date1);
						date2 = moment(date2);
						if ( date1 <= date2 ) {
							$("#form_submit").removeAttr("disabled").css({'background-color': '#3366CC','color': 'white'});
						} else {
							$("#form_submit").attr("disabled", "disabled").css({'background-color':'#c8ccd1','color':'#fff'});
						}
					}
				});
			}
		}

		function resetDateAndTime() {
			eventDateDay = '01';
			eventDateYear = '2010';
			eventDateMonth = '00';
			eventDateHour = '00';
			eventDateMinute = '00';
			eventDateSecond = '00';
			eventDateAmPm24h = "";
		}

		// flag : 0 - one day , 1 - start, 2 - end
		function setDateAndTime( arrData, flag ) {
			for( dateEntry=0; dateEntry < arrData.length ; dateEntry++ ) {
				if( arrData[dateEntry].name.includes('year') ) {
					eventDateYear = arrData[dateEntry].value;
				} else if ( arrData[dateEntry].name.includes('month') ) {
					eventDateMonth = padNumber(arrData[dateEntry].value);

				} else if ( arrData[dateEntry].name.includes('day') ) {
					eventDateDay = padNumber( arrData[dateEntry].value ) ;
				} else if ( arrData[dateEntry].name.includes('hour') ) {
					eventDateHour = arrData[dateEntry].value ;
					if( eventDateHour === '' ) {
						if ( flag === '1' ) {
							eventDateHour = '00';
						} else {
							eventDateHour = '23';
						}
					}
				} else if ( arrData[dateEntry].name.includes('minute') ) {
					eventDateMinute = arrData[dateEntry].value ;
					if( eventDateMinute === '' ) {
						if ( flag === '1' ) {
							eventDateMinute = '00';
						} else {
							eventDateMinute = '59';
						}
					}
				} else if ( arrData[dateEntry].name.includes('second') ) {
					eventDateSecond = arrData[dateEntry].value ;
					if( eventDateSecond === '' ) {
						if ( flag === '1' ) {
							eventDateSecond = '00';
						} else {
							eventDateSecond = '59';
						}
					}
				} else if ( arrData[dateEntry].name.includes('ampm24h') ) {
					eventDateAmPm24h = arrData[dateEntry].value ;
				}
			}
		}

		function checkDateTime( arrData, date ) {
			if( arrData.length === 7 ) {
				if( eventDateAmPm24h === "" ) {
					date = date + 'T' + padNumber( eventDateHour ) + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
				} else if ( eventDateAmPm24h === "AM" ) {
					if( eventDateHour === "12" ) {
						date = date + 'T' + '00' + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
					} else {
							date = date + 'T' + padNumber( eventDateHour ) + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
					}
				} else if ( eventDateAmPm24h === "PM" ) {
					if( eventDateHour === "12" ) {
						date = date + 'T' + '12' + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
					} else {
							date = date + 'T' + padNumber( parseInt(eventDateHour) + 12 ) + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
					}
				}
			}
			return date;
		}

		// Check if the date/datetime formats are suitable for the
		// calendar eventStartDate - if not, these are not proper
		// events, and they will be stored in eventsNoDate.
		// The values are stored as ISO dates (YYYY-MM-DD), see FormDate.
		function isValidDate(dateString) {
			if ( !/^\d{4}-\d{2}-\d{2}$/.test( dateString ) ) {
				return false;
			}
			const d = new Date( dateString );
			return !isNaN( d.getTime() ) && d.toISOString().slice( 0, 10 ) === dateString;
		}

		// A date and time is stored as YYYY-MM-DD HH:MM:SS, optionally followed by a timezone,
		// or as YYYY-MM-DDTHH:MM:SSZ.
		function dateTimeValidation(dateString) {
			return /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?: ?\S+)?$/.test( dateString ) &&
				isValidDate( dateString.slice( 0, 10 ) );
		}

		// Splits a stored date and time into its parts, with the hour in the form the
		// popup shows it: 24 hour, or 12 hour with AM/PM if the wiki does not use 24 hour time.
		function splitDateTime( dateString ) {
			const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/.exec( dateString );
			const parts = { year: m[1], month: m[2], day: m[3], hour: '', minute: '', second: '', ampm24h: '' };
			if ( m[4] !== undefined ) {
				const hour24 = parseInt( m[4], 10 );
				if ( mw.config.get( 'wgPageForms24HourTime' ) ) {
					parts.hour = m[4];
				} else {
					parts.hour = padNumber( hour24 % 12 === 0 ? 12 : hour24 % 12 );
					parts.ampm24h = hour24 >= 12 ? 'PM' : 'AM';
				}
				parts.minute = m[5];
				parts.second = m[6];
			}
			return parts;
		}

		// The value to store, from the parts of the popup form. The hour is read as 12 hour if there is an AM/PM.
		function joinDateTime( year, month, day, hour, minute, second, ampm ) {
			const date = year + '-' + padNumber( month ) + '-' + padNumber( day );
			if ( hour === '' ) {
				return date;
			}
			let hour24 = parseInt( hour, 10 );
			if ( ampm.trim() !== '' && hour24 >= 1 && hour24 <= 12 ) {
				hour24 = hour24 % 12 + ( ampm.trim() === 'PM' ? 12 : 0 );
			}
			return date + ' ' + padNumber( hour24 ) + ':' + padNumber( minute ) + ':' + padNumber( second );
		}

		function addDays(myDate,days) {
			return new Date(myDate.getTime() + days*24*60*60*1000);
		}

		function padNumber(number) {
			let string = '' + number;
			string = string.length < 2 ? '0' + string : string;
			return string;
		}

		$( calendarIdSelector ).fullCalendar({

			editable: true,
			eventLimit: true, // when too many events in a day, show the popover
			header: {
				left: 'prev,next today',
				center: 'title',
				right: 'month,agendaWeek,agendaDay'
			},
			navLinks: true, // can click day/week names to navigate views
			selectable: true,
			selectHelper: true,
			nextDayThreshold: "00:00:00",

			// Populate the calendar with the already saved events - if any
			events: function( start, end, timezone, callback ) {
				const calendarValues = calendarGridValues[templateName];
				for( i = 0; i < calendarValues.length; i++ ) {
					data = [];
					eventData = calendarValues[i];
					for( j=0; j<calendarParams[templateName].length;j++ ) {
						currParam = calendarParams[templateName][j];
						temp = eventData[currParam.name];
						if( fieldType[currParam.name] === 'date' && isValidDate(temp) === false ) {
							eventsNoDate.push(eventData);
							suitableForCalendar = false;
						} else if ( fieldType[currParam.name] === 'datetime' && dateTimeValidation(temp) === false ) {
							eventsNoDate.push(eventData);
							suitableForCalendar = false;
						}
					}
					if(suitableForCalendar === true ) {
						for( j=0; j<calendarParams[templateName].length;j++ ) {
							currParam = calendarParams[templateName][j];
							temp = eventData[currParam.name];
							if( fieldType[currParam.name] === 'date' ) {
								dateSegment = temp.split('-');
								yearFC = dateSegment[0];
								monthFC = dateSegment[1];
								dateFC = padNumber(dateSegment[2]);
								dateEntry = {
									'name': templateName + '[cf]['+currParam.name+'][day]',
									'value': dateFC
								};
								data.push( dateEntry );
								monthEntry = {
									'name': templateName + '[cf]['+currParam.name+'][month]',
									'value': monthFC
								};
								data.push( monthEntry );
								yearEntry = {
									'name': templateName + '[cf]['+currParam.name+'][year]',
									'value': yearFC
								};
								data.push( yearEntry );
							} else if ( fieldType[currParam.name] === "datetime" ) {
								segment = splitDateTime( temp );
								yearFC = segment.year;
								monthFC = segment.month;
								dateFC = segment.day;
								hourFC = segment.hour;
								minuteFC = segment.minute;
								secondFC = segment.second;
								ampm24h = segment.ampm24h;

								dateEntry = {
									'name': templateName+'[cf]['+currParam.name+'][day]',
									'value': dateFC
								};
								data.push( dateEntry );
								monthEntry = {
									'name': templateName+'[cf]['+currParam.name+'][month]',
									'value': monthFC
								};
								data.push( monthEntry );
								yearEntry = {
									'name': templateName+'[cf]['+currParam.name+'][year]',
									'value': yearFC
								};
								data.push(yearEntry);
								hourEntry = {
									'name': templateName+'[cf]['+currParam.name+'][hour]',
									'value': hourFC
								};
								data.push( hourEntry );
								minuteEntry = {
									'name': templateName+'[cf]['+currParam.name+'][minute]',
									'value': minuteFC
								};
								data.push( minuteEntry );
								secondEntry = {
									'name': templateName+'[cf]['+currParam.name+'][second]',
									'value': secondFC
								};
								data.push( secondEntry );
								ampm24hEntry = {
									'name': templateName+'[cf]['+currParam.name+'][ampm24h]',
									'value': ampm24h
								};
								data.push( ampm24hEntry );
							} else {
								regularEntry = {
									'name': templateName+'[cf]['+currParam.name+']',
									'value':temp
								};
								data.push( regularEntry );
							}
						}
					if( flagOneDayEvent === true ) {
						titleIndex = -1;
						dateFields = [];
						eventDate = '';
						for(j=0;j<data.length;j++){
							result = data[j].name.split("[");
							eventTemplateName = result[0];
							parameterName = result[2].split("]")[0];

							if ( parameterName === eventTitleField ) {
								if( titleIndex === -1 ) {
									titleIndex = j;
								}
							} else if ( parameterName === eventDateField ) {
								dateFields.push( {'name':data[j].name, 'value':data[j].value} );
							}
						}
						resetDateAndTime();
						setDateAndTime( dateFields );
						eventDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventDate;
						eventDate = checkDateTime( dateFields, eventDate );
						idForm = eventDate + "_fc" + counter;
						preEventData = {
							title: data[titleIndex].value,
							start: eventDate,
							end: reserveDate + 'T23:59:59',
							contents: data,
							id: idForm
						};
						if(!pageLoaded){
							counter++;
							$( calendarIdSelector ).fullCalendar( 'renderEvent', preEventData, true );
						}
					} else {
						titleIndex = -1;
						dateStartFields = [];
						dateEndFields = [];
						eventStartDate = '';
						eventEndDate = '';
						for( j = 0; j < data.length; j++ ){
							result = data[j].name.split("[");
							eventTemplateName = result[0];
							parameterName = result[2].split("]")[0];

							if ( parameterName === eventTitleField ) {
								if( titleIndex === -1 ) {
									titleIndex = j;
								}
							} else if ( parameterName === eventStartDateField ) {
								dateStartFields.push({'name': data[j].name, 'value': data[j].value});
							} else if ( parameterName === eventEndDateField ) {
								dateEndFields.push({'name': data[j].name, 'value': data[j].value});
							}
						}
						resetDateAndTime();
						setDateAndTime( dateStartFields );
						eventStartDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventStartDate;
						eventStartDate = checkDateTime( dateStartFields, eventStartDate );
						resetDateAndTime();
						setDateAndTime( dateEndFields );
						eventEndDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventEndDate;
						eventEndDate = checkDateTime( dateEndFields, eventEndDate );
						idForm = eventStartDate +"_fc"+counter;
						if ( fieldType[eventEndDateField] === 'date' ) {
							dateElement = new Date(eventEndDate);
							nextDate = new Date(dateElement.setDate(dateElement.getDate() + 1));
							formatted = nextDate.getUTCFullYear() + '-' + padNumber(nextDate.getUTCMonth() + 1) + '-' + padNumber(nextDate.getUTCDate());
							eventEndDate = formatted;
						}
						preEventData = {
							title: eventData[eventTitleField],
							start: eventStartDate,
							end: eventEndDate,
							contents: data,
							id: idForm
						};
						if(!pageLoaded){
							counter++;
							$( calendarIdSelector ).fullCalendar( 'renderEvent', preEventData, true );
						}

					}
				}
				suitableForCalendar = true;
				}
				callback(events);
				pageLoaded = true;
			},

			// Select : JS method to put events on the calendar by selecting
			// one or more dates
			select: function( start, end ) {
				// Open the popup form to allow the user to create the event
				$.fancybox.open( createEventPopup + '</form>' );
				$("[class|='fancybox-close-small']").attr("type", "button");
				// Handle token input type
				$(':input').each( function() {
					tokensProto = new pf.select2.tokens();
					if ( this.classList.contains( 'pfTokens' ) ){
						tokensProto.apply( $(this) );
					}
				});
				// Handling the text with autocomplete
				$('#popupForm').find(".autocompleteInput").each( function() {
					$(this).attachAutocomplete();
				});
				// Handle combobox input type
				// $(':input').each( function() {
				// 	comboboxProto = new pf.select2.combobox();
				// 	if ( $( this ).hasClass( 'pfComboBox' ) ){
				// 		comboboxProto.apply( $(this) );
				// 	}
				// });

				// Handle the tree input types
				$('#popupForm').find(".pfTreeInput").each( function() {
					$(this).applyJSTree();
				});
				$('#popupForm').find(".pfRating").each( function() {
					$(this).applyRatingInput();
				});
				// Check if the event is only one day long
				// For current code - it is required to see if the event is one day long or not.
				// In future the code can be reduced and this if-else condition can be removed
				if ( flagOneDayEvent === true ) {
					idForm = start.format() + "_fc" + counter;
					// Atuomatically set the event date value
					$( ':input[name="' + autoFillDay + '"]' ).val( start.format( 'DD' ) );
					$( ':input[name="' + autoFillYear + '"]' ).val( start.format( 'YYYY' ) );
					$( ':input[name="' + autoFillMonth + '"]' ).val( start.format( 'MM' ) );

					checkAndSave( 'single' );
					// Save all the data of the popup form and set the title, event date and the unique ID of the event
					$( "#form_submit" ).click(( event ) => {
						saveData( 'single' );
						resetDateAndTime();
						setDateAndTime( dateFields );
						eventDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventDate;
						eventDate = checkDateTime( dateFields, eventDate );
						eventData = {
							title: data[titleIndex].value,
							start: eventDate,
							end: reserveDate + 'T23:59:59',
							contents: data,
							id:idForm
						};

						counter++;
						$( '.fancybox-close-small' ).click();
						$( calendarIdSelector ).fullCalendar( 'renderEvent', eventData, true );
					});
				} else {
					idForm = start.format() + "_fc" + counter;
					currentEndDateMoment = moment(end);
					currentEndDateMoment = currentEndDateMoment.subtract(1 , 'days');
					$( ':input[name="' + autoFillStartDay + '"]' ).val( Number(start.format( 'DD' )) );
					$( ':input[name="' + autoFillStartYear + '"]' ).val( start.format( 'YYYY' ) );
					$( ':input[name="' + autoFillStartMonth + '"]' ).val( start.format( 'MM' ) );

					$( ':input[name="' + autoFillEndDay + '"]' ).val( Number(currentEndDateMoment.format( 'DD' )) );
					$( ':input[name="' + autoFillEndYear + '"]' ).val( currentEndDateMoment.format( 'YYYY' ) );
					$( ':input[name="' + autoFillEndMonth + '"]' ).val( currentEndDateMoment.format( 'MM' ) );


					checkAndSave( 'multiple' );
					$( "#form_submit" ).click(( event ) => {
						saveData( 'multiple' );
						resetDateAndTime();
						setDateAndTime( dateStartFields, '1' );
						eventStartDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventStartDate;
						eventStartDate = checkDateTime( dateStartFields, eventStartDate );
						resetDateAndTime();
						setDateAndTime( dateEndFields, '2' );
						eventEndDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventEndDate;
						eventEndDate = checkDateTime( dateEndFields, eventEndDate );

						if ( fieldType[eventEndDateField] === 'date' ) {
							dateElement = new Date(eventEndDate);
							nextDate = new Date(dateElement.setDate(dateElement.getDate() + 1));
							formatted = nextDate.getUTCFullYear() + '-' + padNumber(nextDate.getUTCMonth() + 1) + '-' + padNumber(nextDate.getUTCDate());
							eventEndDate = formatted;
						}
						eventData = {
							title: data[titleIndex].value,
							start: eventStartDate,
							end: eventEndDate,
							resourceEditable:true,
							contents: data,
							id:idForm
						};
						counter++;
						$( '.fancybox-close-small' ).click();
						$( calendarIdSelector ).fullCalendar( 'renderEvent', eventData, true );
					});
				}
			},

			// // Edit an event placed on the calendar by simply clicking on it
			eventClick: function( info ) {
				const content = $( calendarIdSelector ).fullCalendar( 'clientEvents', info.id );
				const formContents = content[0].contents;
				const ratingArr = [];
				checkboxesValues = [];
				let paramName, rateSample = 0;
				// Open the popup form and populate it with the values to allow editing
				$.fancybox.open( updateEventPopup + deleteButton + '</form>' );
				$("[class|='fancybox-close-small']").attr("type", "button");

				$('#popupForm').find(".pfTreeInput").each( function() {
					$(this).applyFancytree();
				});

				// Prepare the popup form for editing
				for( i = 0; i < formContents.length; i++ ) {
					temp = formContents[i].name;
					paramName = temp.split('[')[2].split(']')[0];
					// If there is a month field, set according to the date format
					if ( temp.includes('month') ) {
						$(':input[name="'+temp+'"]').val(formContents[i].value);

					} else if ( temp.includes('day') ) {
						$(':input[name="'+temp+'"]').val( Number(formContents[i].value) );
					} else {
						if(fieldType[paramName] === 'radiobutton' || fieldType[paramName] === 'checkbox' || fieldType[paramName] === 'checkboxes' || fieldType[paramName] === 'listbox' ) {
							if( fieldType[paramName] === 'radiobutton' ) {
								$(':input[value="' + formContents[i].value + '"]').attr('checked',true);
							}
							if( fieldType[paramName] === 'checkbox' && !temp.includes('[is_checkbox]')) {
								temp = temp.replace('[value]','');
								temp = temp.replace('[is_checkbox]','');
								temp+='[is_checkbox]';
								const checkId1 = ($(':hidden[name="'+temp+'"]')[0].nextElementSibling.id);
								$('#'+ checkId1).prop('checked',formContents[i].value);
								$(':hidden[name="'+temp+'"]').attr('value',0);
							}
							if( fieldType[paramName] === 'checkboxes' && !temp.includes('[is_list]')) {
								if( formContents[i].value.includes(',') ) {
									checkboxesValues = formContents[i].value.split(', ');
									for( let p = 0; p<checkboxesValues.length; p++ ) {
										$(':input[value="' + checkboxesValues[p] + '"]').attr('checked',true);
									}
								} else {
									$(':input[value="' + formContents[i].value + '"]').attr('checked',true);
									// checkboxes_values.push(formContents[i].value);
								}
							}
							if( fieldType[paramName] === 'listbox' && !temp.includes('[is_list]')) {
								if( formContents[i].value.includes(',') ) {
									listboxValues = formContents[i].value.split(', ');
									for( let list = 0; list<listboxValues.length; list++ ) {
										$('option[value="' + listboxValues[list] + '"]').attr("selected", "selected");
									}
								} else {
									$('option[value="' + formContents[i].value + '"]').attr("selected", "selected");
									// checkboxes_values.push(form_contents[i].value);
								}
							}
						} else {
							$(':input[name="'+temp+'"]').val( formContents[i].value );
						}
						if( fieldType[paramName] === 'rating' ) {
							ratingArr.push( formContents[i].value );
						}
					}
					// checkboxesNum.push(checkboxes_values);
				}
				// This is my little experiment to include rating input type to the calendar interface
				// This can improve in future version of this file.
				// I have saved the rating values in the ratingArr and just filling it in the 'each'
				// This may seem like a risky bet..
				$('#popupForm').find(".pfRating").each( function() {
					$(this).applyRatingInput( ratingArr[rateSample] );
					rateSample++;
				});
				// Handling the text with autocomplete
				$('#popupForm').find(".autocompleteInput").each( function() {
					$(this).attachAutocomplete();
				});

				// Handling token input type
				$(':input').each(function() {
					tokensProto = new pf.select2.tokens();
					if ( this.classList.contains( 'pfTokens' ) ) {
						tokensProto.apply( $(this) );
					}
				});
				// FIXME: This is not yet working correctly - possibly due
				// to z-index of fancybox popup or select2
				// $(':input').each( function() {
				// 	comboboxProto = new pf.select2.combobox();
				// 	if ( $( this ).hasClass( 'pfComboBox' ) ) {
				// 		comboboxProto.apply( $(this) );
				// 	}
				// });
				// Delete button for the existing event
				$( "#event_delete" ).click(( event ) => {
					$( calendarIdSelector ).fullCalendar('removeEvents',info.id);
					$( '.fancybox-close-small' ).click();
				});

				if ( flagOneDayEvent === true ) {
					// Check if the date fields are left empty or not
					checkAndSave( 'single' );
					// Save everything again once the submit button is pressed
					$( "#form_submit" ).click(( event ) => {
						saveData( 'single' );
						resetDateAndTime();
						setDateAndTime( dateFields );
						eventDate = eventDateYear + '-' + padNumber(eventDateMonth) + '-' + padNumber(eventDateDay);
						eventDate = checkDateTime( dateFields, eventDate );
						info.title = data[titleIndex].value;
						info.contents = data;
						info.start = eventDate;
						$('.fancybox-close-small').click();
						$( calendarIdSelector ).fullCalendar( 'updateEvent' , info , true );
					});
				} else {
					const end = info.end;
					const start = info.start;
					checkAndSave( 'multiple' );
					$( "#form_submit" ).click(( event ) => {
						saveData( 'multiple' );
						resetDateAndTime();
						setDateAndTime( dateStartFields , '1' );
						eventStartDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						eventStartDate = checkDateTime( dateStartFields, eventStartDate );

						resetDateAndTime();
						setDateAndTime( dateEndFields , '2' );
						eventEndDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						eventEndDate = checkDateTime( dateEndFields, eventEndDate );

						if ( fieldType[eventEndDateField] === 'date' ) {
							dateElement = new Date(eventEndDate);
							nextDate = new Date(dateElement.setDate(dateElement.getDate() + 1));
							formatted = nextDate.getUTCFullYear() + '-' + padNumber(nextDate.getUTCMonth() + 1) + '-' + padNumber(nextDate.getUTCDate());
							eventEndDate = formatted;
						}
						info.title = data[titleIndex].value;
						info.contents = data;
						info.start = eventStartDate;
						info.end = eventEndDate;
						$( '.fancybox-close-small') .click();
						$( calendarIdSelector ).fullCalendar( 'updateEvent', info, true );

					});
				}
			},

			eventResize: function(event) {
				event.start._i = event.start.format();
				event.end._i = event.end.format();
				currentEndDateMoment = moment(event.end);
				if( fieldType[eventStartDateField] === 'date' && event.allDay ) {
					currentEndDateMoment = currentEndDateMoment.subtract(1 , 'days');
				}

				eventContents = event.contents;
				for ( i = 0; i < eventContents.length; i++ ) {
					if ( eventContents[i].name === autoFillStartDay ) {
						eventContents[i].value = event.start.format('DD');
					} else if ( eventContents[i].name === autoFillStartMonth ) {
						eventContents[i].value = event.start.format('MM');

					} else if ( eventContents[i].name === autoFillStartYear ) {
						eventContents[i].value = event.start.format('YYYY');
					} else if ( eventContents[i].name === autoFillEndDay ) {
						eventContents[i].value = currentEndDateMoment.format('DD');
					} else if ( eventContents[i].name === autoFillEndMonth ) {
						eventContents[i].value = currentEndDateMoment.format('MM');

					} else if ( eventContents[i].name === autoFillEndYear ) {
						eventContents[i].value = currentEndDateMoment.format('YYYY');
					} else if ( eventContents[i].name === autoFillStartHour ) {
						eventContents[i].value = event.start.format('hh');
					} else if ( eventContents[i].name === autoFillStartMinute ) {
						eventContents[i].value = event.start.format('mm');
					} else if ( eventContents[i].name === autoFillStartSecond ) {
						eventContents[i].value = event.start.format('ss');
					} else if ( eventContents[i].name === autoFillStartAmPm24h ) {
						eventContents[i].value = ( event.start.format('t') === 'p' ? 'PM' : 'AM' );
					} else if ( eventContents[i].name === autoFillEndHour ) {
						eventContents[i].value = event.end.format('hh');
					} else if ( eventContents[i].name === autoFillEndMinute ) {
						eventContents[i].value = event.end.format('mm');
					} else if ( eventContents[i].name === autoFillEndSecond ) {
						eventContents[i].value = event.end.format('ss');
					} else if ( eventContents[i].name === autoFillEndAmPm24h ) {
						eventContents[i].value = ( event.end.format('t') === 'p' ? 'PM' : 'AM' );
					}
				}

				event.contents = eventContents;
				$( calendarIdSelector ).fullCalendar('updateEvent',event);

			},

			eventDrop: function( event, delta, revertFunc ) {
				const oldEvent = $( calendarIdSelector ).fullCalendar( 'clientEvents', event.id );
				$( calendarIdSelector ).fullCalendar('removeEvents',oldEvent[0].id);
				event.start._i = event.start.format();

				if ( flagOneDayEvent === false ) {
					if ( event.end !== null ) {
						event.end._i = event.end.format() ;
						currentEndDateMoment = moment(event.end);
						if( fieldType[eventStartDateField] === 'date' && event.allDay ) {
							currentEndDateMoment = currentEndDateMoment.subtract(1 , 'days');
						}
						eventContents = event.contents;
						for( i = 0; i<eventContents.length; i++ ) {
							if ( eventContents[i].name === autoFillStartDay ) {
								eventContents[i].value = event.start.format('DD');
							} else if ( eventContents[i].name === autoFillStartMonth ) {
								eventContents[i].value = event.start.format('MM');

							} else if ( eventContents[i].name === autoFillStartYear ) {
								eventContents[i].value = event.start.format('YYYY');
							} else if ( eventContents[i].name === autoFillEndDay ) {
								eventContents[i].value = currentEndDateMoment.format('DD');
							} else if ( eventContents[i].name === autoFillEndMonth ) {
								eventContents[i].value = currentEndDateMoment.format('MM');

							} else if ( eventContents[i].name === autoFillEndYear ) {
								eventContents[i].value = currentEndDateMoment.format('YYYY');
							} else if ( eventContents[i].name === autoFillStartHour ) {
								eventContents[i].value = event.start.format('hh');
							} else if ( eventContents[i].name === autoFillStartMinute ) {
								eventContents[i].value = event.start.format('mm');
							} else if ( eventContents[i].name === autoFillStartSecond ) {
								eventContents[i].value = event.start.format('ss');
							} else if ( eventContents[i].name === autoFillStartAmPm24h ) {
								eventContents[i].value = ( event.start.format('t') ==='p' ? 'PM' : 'AM' );
							} else if ( eventContents[i].name === autoFillEndHour ) {
								eventContents[i].value = event.end.format('hh');
							} else if ( eventContents[i].name === autoFillEndMinute ) {
								eventContents[i].value = event.end.format('mm');
							} else if ( eventContents[i].name === autoFillEndSecond ) {
								eventContents[i].value = event.end.format('ss');
							} else if ( eventContents[i].name === autoFillEndAmPm24h ) {
								eventContents[i].value = ( event.end.format('t') ==='p' ? 'PM' : 'AM' );
							}
						}
					} else {
						eventContents = event.contents;
						for ( i = 0; i < eventContents.length; i++ ) {
							if ( eventContents[i].name === autoFillStartDay ) {
								eventContents[i].value = event.start.format('DD');
							} else if ( eventContents[i].name === autoFillStartMonth ) {
								eventContents[i].value = event.start.format('MM');

							} else if ( eventContents[i].name === autoFillStartYear ) {
								eventContents[i].value = event.start.format('YYYY');
							} else if ( eventContents[i].name === autoFillEndDay ) {
								eventContents[i].value = event.start.format('DD');
							} else if ( eventContents[i].name === autoFillEndMonth ) {
								eventContents[i].value = event.start.format('MM');

							} else if ( eventContents[i].name === autoFillEndYear ) {
								eventContents[i].value = event.start.format('YYYY');
							}
						}
					}

				} else {
					eventContents = event.contents;
					event.end._i = event.start.format( 'YYYY' ) + '-' + event.start.format('MM') + '-' + event.start.format('DD') + "T23:59:59";
					for( i = 0; i<eventContents.length; i++ ) {
						if ( eventContents[i].name === autoFillDay ) {
							eventContents[i].value = event.start.format('DD');
						} else if ( eventContents[i].name === autoFillMonth ) {
							eventContents[i].value = event.start.format('MM');

						} else if ( eventContents[i].name === autoFillYear ) {
							eventContents[i].value = event.start.format('YYYY');
						} else if ( eventContents[i].name === autoFillHour ) {
							eventContents[i].value = event.start.format('hh');
						} else if ( eventContents[i].name === autoFillMinute ) {
							eventContents[i].value = event.start.format('mm');
						} else if ( eventContents[i].name === autoFillSecond ) {
							eventContents[i].value = event.start.format('ss');
						} else if ( eventContents[i].name === autoFillAmPm24h ) {
							eventContents[i].value = ( event.start.format('t') ==='p' ? 'PM' : 'AM' );
						}
					}
				}
				$( calendarIdSelector ).fullCalendar( 'renderEvent', event, true );
			},
			displayEventEnd: isEventEndDateTime,
			timeFormat: 'H(:mm:ss)t',
			eventDurationEditable: true,
			displayEventTime: isEventStartTime
		});
		$('#fullCalendarLoading').css("display", "none");

		// Handle the "Save page" button
		$( "#pfForm" ).submit(( event ) => {
			allEvents = $( calendarIdSelector ).fullCalendar('clientEvents');
			let dateValue = '';
			let day = '';
			let month = '';
			let year = '';
			let hour = '';
			let minute = '';
			let second = '';
			ampm24h = ' ';
			for( i =0;i<allEvents.length;i++ ) {
				const eventContent = allEvents[i].contents;
				const finalFieldValues = [];
				for( let ii=0; ii < calendarParams[templateName].length; ii++ ) {
					parameterName = calendarParams[templateName][ii].name;
					let inputValue = '';
					if( fieldType[parameterName] === "date" ) {
						dateValue = '';
						day = '';
						month = '';
						year = '';
						for ( j = 0; j < eventContent.length; j++ ) {
							if( eventContent[j].name.includes('['+parameterName+']') ) {
								if( eventContent[j].name.includes('[day]') ) {
									day = eventContent[j].value.replace(/(^|-)0+/g, "$1");
								} else if( eventContent[j].name.includes('[year]') ) {
									year = eventContent[j].value;
								} else if( eventContent[j].name.includes('[month]') ) {
									month = eventContent[j].value;
								}
							}
						}
						if ( year !== '' && month !== '' && day !== '' ) {
							dateValue = joinDateTime( year, month, day, '', '', '', '' );
						}
						inputValue = dateValue;
					} else if ( fieldType[parameterName] === "datetime" ) {
						dateValue = '';
						day = '';
						month = '';
						year = '';
						hour = '';
						minute = '';
						second = '';
						ampm24h = ' ';
						for( j = 0; j < eventContent.length; j++ ) {
							if( eventContent[j].name.includes('['+parameterName+']') ) {
								if( eventContent[j].name.includes('[day]') ) {
									day = eventContent[j].value.replace(/(^|-)0+/g, "$1");
								} else if( eventContent[j].name.includes('[year]') ) {
									year = eventContent[j].value;
								} else if( eventContent[j].name.includes('[month]') ) {
									month = eventContent[j].value;
								} else if( eventContent[j].name.includes('[hour]') ) {
									hour = eventContent[j].value;
								} else if( eventContent[j].name.includes('[minute]') ) {
									minute = eventContent[j].value;
								} else if( eventContent[j].name.includes('[second]') ) {
									second = eventContent[j].value;
								} else if( eventContent[j].name.includes('[ampm24h]') ) {
									ampm24h = (eventContent[j].value !== '' ) ? eventContent[j].value : ' ';
								}
							}
						}
						if ( year !== '' && month !== '' && day !== '' ) {
							dateValue = joinDateTime( year, month, day, hour, minute, second, ampm24h );
						}
						inputValue = dateValue;
					} else {
						let checkboxesFinal = '';
						checkboxesNum= [];
						if ( fieldType[parameterName] === 'checkboxes' || fieldType[parameterName] === 'listbox' ) {
							for ( j = 0; j < eventContent.length; j++ ) {
								if ( eventContent[j].name.includes('['+parameterName+']') && !eventContent[j].name.includes('[is_list]') ) {
									checkboxesNum.push(eventContent[j].value);
								}
							}
							for ( let t = 0; t < checkboxesNum.length; t++ ) {
								if( t < checkboxesNum.length -1 ) {
									checkboxesFinal += checkboxesNum[t] + ', ';
								} else {
									checkboxesFinal += checkboxesNum[t];
								}
							}
							inputValue = checkboxesFinal;
						} else {
							for ( j = 0; j < eventContent.length; j++ ) {
								if ( eventContent[j].name.includes('['+parameterName+']') ) {
									inputValue = eventContent[j].value;
								}
							}
						}
					}
					const inputName = templateName + '['+ (i+1) +'][' + parameterName + ']';
					finalFieldValues[inputName] = inputValue;
					$('<input>').attr( 'type', 'hidden' ).attr( 'name', inputName ).attr( 'value',finalFieldValues[inputName] ).appendTo( '#pfForm' );
				}
			}
			for ( let k = 0; k < eventsNoDate.length; k++ ) {
				const index = i+1;
				for( let jj = 0; jj < calendarParams[templateName].length; jj++ ) {
					parameterName = calendarParams[templateName][jj].name;
					const entryName = templateName + '['+ (index) +'][' + parameterName + ']';
					$('<input>').attr( 'type', 'hidden' ).attr( 'name', entryName ).attr( 'value',eventsNoDate[k][parameterName] ).appendTo( '#pfForm' );
				}
			}
		});

	});
}( jQuery, mediaWiki, pf ) );
