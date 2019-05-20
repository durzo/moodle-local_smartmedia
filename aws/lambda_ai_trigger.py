'''
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

@copyright   2019 Matt Porritt <mattp@catalyst-au.net>
@license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

'''

import boto3
import botocore
import os
import logging
import io
import json
from botocore.exceptions import ClientError

s3_client = boto3.client('s3')
rekognition_client = boto3.client('rekognition')
logger = logging.getLogger()


def lambda_handler(event, context):
    """
    lambda_handler is the entry point that is invoked when the lambda function is called,
    more information can be found in the docs:
    https://docs.aws.amazon.com/lambda/latest/dg/python-programming-model-handler-types.html

    Trigger the file conversion when the source file is uploaded to the input s3 bucket.
    """

    #  Set logging
    logging_level = os.environ.get('LoggingLevel', logging.ERROR)
    logger.setLevel(int(logging_level))

    # Get output bucket
    output_bucket = os.environ.get('OutputBucket')
    rekognition_Complete_Role_arn = os.environ.get('RekognitionCompleteRoleArn')
    sns_rekognition_complete_arn = os.environ.get('SnsTopicRekognitionCompleteArn')

    for record in event['Records']:
        sns_message_json = record['Sns']['Message']
        sns_message_object = json.loads(sns_message_json)
        input_key = sns_message_object['input']['key']
        output_key_prefix = sns_message_object['outputKeyPrefix']
        job_id = sns_message_object['jobId']

        rekognition_input = '{}/conversions/{}.mp4'.format(input_key, input_key)

        # Start Rekognition Label extraction.
        logger.info('Starting Rekognition label detection')
        label_response = rekognition_client.start_label_detection(
             Video={
                'S3Object': {
                    'Bucket': output_bucket,
                    'Name': rekognition_input
                }
            },
            ClientRequestToken=job_id,
            MinConfidence=80,  # 50 is default.
            NotificationChannel={
                'SNSTopicArn': sns_rekognition_complete_arn,
                'RoleArn': rekognition_Complete_Role_arn
            },
            JobTag=job_id
            )

        logging.error(label_response)

        # Start Rekognition content moderation operatations.
        logger.info('Starting Rekognition moderation detection')
        moderation_response = rekognition_client.start_content_moderation(
            Video={
                'S3Object': {
                    'Bucket': output_bucket,
                    'Name': rekognition_input
                }
            },
            MinConfidence=80,  # 50 is default.
            ClientRequestToken=job_id,
            NotificationChannel={
                'SNSTopicArn': sns_rekognition_complete_arn,
                'RoleArn': rekognition_Complete_Role_arn
            },
            JobTag=job_id
            )

        logging.error(moderation_response)

        # Start Rekognition face detection.
        logger.info('Starting Rekognition face detection')
        face_response = rekognition_client.start_face_detection(
            Video={
                'S3Object': {
                    'Bucket': output_bucket,
                    'Name': rekognition_input
                }
            },
            ClientRequestToken=job_id,
            NotificationChannel={
                'SNSTopicArn': sns_rekognition_complete_arn,
                'RoleArn': rekognition_Complete_Role_arn
            },
            FaceAttributes='DEFAULT',  # Other option is ALL.
            JobTag=job_id
        )

        logger.error(face_response)

        # Start Rekognition Person tracking.
        logger.info('Starting Rekognition person tracking')
        person_tracking_response = rekognition_client.start_person_tracking(
            Video={
                'S3Object': {
                    'Bucket': output_bucket,
                    'Name': rekognition_input
                }
            },
            ClientRequestToken=job_id,
            NotificationChannel={
                'SNSTopicArn': sns_rekognition_complete_arn,
                'RoleArn': rekognition_Complete_Role_arn
            },
            JobTag=job_id
        )

        logger.error(person_tracking_response)
